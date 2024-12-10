<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\projects\ProjectInterface;

/**
 * The project complete form provides a simple UI to change the lifecycle state.
 */
class ProjectCompleteForm extends ProjectActionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_complete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL) {

    // Set title for form.
    $form['#title'] = $this->t('Complete Project: %s', [
      '%s' => $project->getTitle(),
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Complete Project'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /** @var \Drupal\projects\Entity\Project $project */
    $project = $form_state->getValues()['project'];

    // Complete project.
    if ($project->lifecycle()->complete()) {
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectCompleteEvent($project));
      $this->messenger()->addMessage($this->t('Project was completed successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Could not complete project.'));
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
