<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectInterface;

/**
 * The project archive form provides a simple UI to change the lifecycle state.
 */
class ProjectArchiveForm extends ProjectTransitionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_archive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL): array {

    // Set title for form.
    $form['#title'] = $this->t('Archive Project: %s', [
      '%s' => $project?->getTitle() ?? '',
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['reason'] = [
      '#type' => 'select',
      '#title' => $this->t('Reason'),
      '#empty_option' => $this->t('- None -'),
      '#options' => [
        ProjectArchiveReason::Unattractive->value => $this->t('Project is unattractive'),
        ProjectArchiveReason::Creatives->value => $this->t('Creatives aborted the project'),
        ProjectArchiveReason::External->value => $this->t('External mediation'),
        ProjectArchiveReason::Other->value => $this->t('Other'),
      ],
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Archive Project'),
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
      $event = new ProjectArchiveEvent($project);
      $event->setReason(ProjectArchiveReason::tryFrom((string) $form_state->getValue('reason')));
      $event->setMessage((string) $form_state->getValue('message'));
      $this->eventDispatcher->dispatch($event);
      $this->messenger()->addMessage($this->t('Project was archived successfully.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $form_state->setRedirect('entity.project.canonical', ['project' => $project->id()]);
  }

}
