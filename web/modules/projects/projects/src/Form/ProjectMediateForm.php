<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\ProjectInterface;

/**
 * The ProjectMediateForm provides a simple UI for changing lifecycle state.
 */
class ProjectMediateForm extends ProjectActionFormBase {

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

    $options = [];
    foreach ($project->getApplicants() as $applicant) {
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
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /** @var \Drupal\projects\Entity\Project $project */
    $project = $form_state->getValues()['project'];
    $participants = Checkboxes::getCheckedCheckboxes($form_state->getValues()['select_participants']);

    // Mediate project.
    if ($project->lifecycle()->mediate()) {
      $project->setParticipants($participants);
      if ($manager = $project->getOwner()->getManager()) {
        $project->appendParticipant($manager, 'Manager');
      }
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectMediateEvent($project));
      $this->messenger()->addMessage($this->t('Project was mediated successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Could not mediate project.'));
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
