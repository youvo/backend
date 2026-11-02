<?php

namespace Drupal\projects\Form;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\ProjectInterface;

/**
 * Lets administrators reset a project to a draft.
 */
class ProjectResetForm extends ProjectEventFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function label(): TranslatableMarkup {
    return $this->t('Reset Project');
  }

  /**
   * {@inheritdoc}
   */
  protected function successMessage(): TranslatableMarkup {
    return $this->t('Project was reset successfully.');
  }

  /**
   * {@inheritdoc}
   */
  protected function event(ProjectInterface $project, FormStateInterface $form_state): Event {
    return new ProjectResetEvent($project);
  }

}
