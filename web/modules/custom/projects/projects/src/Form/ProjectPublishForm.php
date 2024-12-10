<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectInterface;

/**
 * The project publish form provides a simple UI to change the lifecycle state.
 */
class ProjectPublishForm extends ProjectActionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_publish_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL) {

    // Set title for form.
    $form['#title'] = $this->t('Publish Project: %s', [
      '%s' => $project->getTitle(),
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Publish Project'),
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

    // Mediate project.
    if ($project->lifecycle()->publish()) {
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectPublishEvent($project));
      $this->messenger()->addMessage($this->t('Project was published successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Could not publish project.'));
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
