<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\Event\ProjectUnarchiveEvent;
use Drupal\projects\ProjectInterface;

/**
 * Lets administrators make an archived project active again.
 */
class ProjectUnarchiveForm extends ProjectTransitionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_unarchive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL): array {

    // Set title for form.
    $form['#title'] = $this->t('Unarchive Project: %s', [
      '%s' => $project?->getTitle() ?? '',
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unarchive Project'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    /** @var \Drupal\projects\Entity\Project $project */
    $project = $form_state->getValues()['project'];

    try {
      $this->eventDispatcher->dispatch(new ProjectUnarchiveEvent($project));
      $this->messenger()->addMessage($this->t('Project was unarchived successfully.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $form_state->setRedirect('entity.project.edit_form', ['project' => $project->id()]);
  }

}
