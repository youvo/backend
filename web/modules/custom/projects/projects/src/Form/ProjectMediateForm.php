<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\ProjectInterface;

/**
 * The ProjectMediateForm provides a simple UI for changing lifecycle state.
 */
class ProjectMediateForm extends ProjectTransitionFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_mediate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL): array {

    // Set title for form.
    $form['#title'] = $this->t('Mediate Project: %s', [
      '%s' => $project?->getTitle() ?? '',
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $options = [];
    $applicants = $project?->getApplicants() ?? [];
    foreach ($applicants as $applicant) {
      $options[$applicant->id()] = $applicant->get('field_name')->value;
    }

    $form['select_participants'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Select Participants'),
      '#required' => 1,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Mediate Project'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    /** @var \Drupal\projects\Entity\Project $project */
    $project = $form_state->getValues()['project'];
    $selected_creatives = Checkboxes::getCheckedCheckboxes($form_state->getValues()['select_participants']);

    try {
      $event = new ProjectMediateEvent($project);
      $event->setCreatives($selected_creatives);
      $this->eventDispatcher->dispatch(new ProjectMediateEvent($project));
      $this->messenger()->addMessage($this->t('Project was mediated successfully.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
