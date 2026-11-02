<?php

namespace Drupal\projects\Form;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\ProjectInterface;

/**
 * Lets administrators mediate an open project to selected applicants.
 */
class ProjectMediateForm extends ProjectEventFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_mediate_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function label(): TranslatableMarkup {
    return $this->t('Mediate Project');
  }

  /**
   * {@inheritdoc}
   */
  protected function successMessage(): TranslatableMarkup {
    return $this->t('Project was mediated successfully.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL): array {

    $form = parent::buildForm($form, $form_state, $project);

    $options = [];
    foreach ($project?->getApplicants() ?? [] as $id => $applicant) {
      $options[$id] = $applicant->get('field_name')->value;
    }
    $form['select_participants'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Select Participants'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function event(ProjectInterface $project, FormStateInterface $form_state): Event {
    $selected = Checkboxes::getCheckedCheckboxes($form_state->getValue('select_participants'));
    $event = new ProjectMediateEvent($project);
    $event->setCreatives(array_intersect_key($project->getApplicants(), array_flip($selected)));
    return $event;
  }

}
