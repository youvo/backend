<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\projects\ProjectInterface;

/**
 * The ProjectMediateForm provides a simple UI for changing lifecycle state.
 */
class ProjectSubmitForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ProjectInterface $project = NULL) {

    // Set title for form.
    $form['#title'] = $this->t('Submit Project: %s', [
      '%s' => $project->getTitle(),
    ]);

    // Store project for submit handler.
    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
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
    if ($project->workflowManager()->transitionMediate()) {
      $project->setParticipants($participants);
      if ($manager = $project->getManager()) {
        $project->appendParticipant($manager, 'Manager');
      }
      $project->save();
      $this->messenger()->addMessage($this->t('Project was mediated successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Could not mediate project.'));
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
