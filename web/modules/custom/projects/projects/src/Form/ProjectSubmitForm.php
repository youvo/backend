<?php

namespace Drupal\projects\Form;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectInterface;

/**
 * Lets administrators submit a draft project.
 */
class ProjectSubmitForm extends ProjectEventFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function label(): TranslatableMarkup {
    return $this->t('Submit Project');
  }

  /**
   * {@inheritdoc}
   */
  protected function successMessage(): TranslatableMarkup {
    return $this->t('Project was submitted successfully.');
  }

  /**
   * {@inheritdoc}
   */
  protected function event(ProjectInterface $project, FormStateInterface $form_state): Event {
    return new ProjectSubmitEvent($project);
  }

}
