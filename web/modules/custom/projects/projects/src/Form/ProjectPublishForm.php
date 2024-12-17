<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectInterface;

/**
 * The project publish form provides a simple UI to change the lifecycle state.
 */
class ProjectPublishForm extends ProjectTransitionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_publish_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL): array {

    // Set title for form.
    $form['#title'] = $this->t('Publish Project: %s', [
      '%s' => $project?->getTitle() ?? '',
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
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    /** @var \Drupal\projects\Entity\Project $project */
    $project = $form_state->getValues()['project'];

    try {
      $this->eventDispatcher->dispatch(new ProjectPublishEvent($project));
      $this->messenger()->addMessage($this->t('Project was published successfully.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
