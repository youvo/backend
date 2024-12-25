<?php

namespace Drupal\projects\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\user\UserInterface;

/**
 * Entity form variant for projects.
 */
class ProjectForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\projects\ProjectInterface $project */
    $project = $this->entity;

    // Disallow access to form widget provided by fields.
    $form['field_participants']['#access'] = FALSE;
    $form['field_participants_tasks']['#access'] = FALSE;

    // Load default values for participants.
    $participants_default = [];
    foreach ($project->getParticipants() as $participant) {
      /** @var string $task */
      // @phpstan-ignore-next-line
      $task = $participant->task;
      $task_id = match ($task) {
        'Manager' => 7,
        default => 3,
      };
      $participants_default[] = [
        'participant' => $participant,
        'task' => $task_id,
      ];
    }

    // Compile participants and respective roles in a single multi-value field.
    $form['multi_participants'] = [
      '#title' => $this->t('Participants'),
      '#type' => 'multivalue',
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#add_more_label' => $this->t('Add participant'),
      '#default_value' => $participants_default,
      'participant' => [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Participant'),
        '#target_type' => 'user',
        '#selection_settings' => [
          'include_anonymous' => FALSE,
        ],
        '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
      ],
      'task' => [
        '#type' => 'select',
        '#options' => [
          '3' => 'Creative',
          '7' => 'Manager',
        ],
        '#title' => $this->t('Task'),
      ],
      '#weight' => -2,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $multi_participants = $form_state->getValues()['multi_participants'];
    foreach ($multi_participants as $multi_participant) {
      if (!empty($multi_participant['participant'])) {
        $participants[] = ['target_id' => $multi_participant['participant']];
        $task = match ($multi_participant['task']) {
          '7' => 'Manager',
          default => 'Creative',
        };
        $participants_tasks[] = $task;
      }
    }
    $form_state->setValue('field_participants', $participants ?? []);
    $form_state->setValue('field_participants_tasks', $participants_tasks ?? []);
    parent::submitForm($form, $form_state);
  }

}
