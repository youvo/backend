<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectInterface;

/**
 * The project submit form provides a simple UI to change the lifecycle state.
 */
class ProjectSubmitForm extends ProjectTransitionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL): array {

    // Set title for form.
    $form['#title'] = $this->t('Submit Project: %s', [
      '%s' => $project?->getTitle() ?? '',
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Project'),
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
      $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
      $this->messenger()->addMessage($this->t('Project was submitted successfully.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
