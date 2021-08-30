<?php

namespace Drupal\youvo_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\youvo_projects\ProjectInterface;

/**
 * The ProjectMediateForm provides a simple UI for changing lifecycle state.
 */
class ProjectMediateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_mediate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ProjectInterface $project = NULL) {

    // Set title for form.
    $form['#title'] = $this->t('Mediate Project: %s', [
      '%s' => $project->getTitle(),
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['select_participants'] = [
      '#type' => 'checkboxes',
      '#options' => $project->getApplicantsAsArray(),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Initialize.
    $project = $form_state->getValues()['project'];
    $participants = Checkboxes::getCheckedCheckboxes($form_state->getValues()['select_participants']);

    // Mediate project.
    if ($project->transitionMediate()) {
      $project->setParticipants($participants, TRUE);
      $this->messenger()->addMessage($this->t('Project was mediated successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Could not mediate project.'));
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
