<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\ProjectInterface;

/**
 * The project reset form provides a simple UI to change the lifecycle state.
 */
class ProjectResetForm extends ProjectActionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ProjectInterface $project = NULL) {

    // Set title for form.
    $form['#title'] = $this->t('Reset Project: %s', [
      '%s' => $project->getTitle(),
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Project'),
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

    // Reset project.
    if ($project->lifecycle()->reset()) {
      $project->setPromoted(FALSE);
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
      $this->messenger()->addMessage($this->t('Project was reset successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Could not reset project.'));
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
