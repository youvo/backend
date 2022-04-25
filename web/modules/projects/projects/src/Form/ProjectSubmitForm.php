<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\ProjectInterface;

/**
 * The project submit form provides a simple UI to change the lifecycle state.
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
      '#value' => $this->t('Submit Project'),
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
    if ($project->workflowManager()->transitionSubmit()) {
      $project->save();
      $this->messenger()->addMessage($this->t('Project was submitted successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Could not submit project.'));
    }

    // Set redirect after submission.
    $form_state->setRedirect('entity.node.canonical', ['node' => $project->id()]);
  }

}
