<?php

namespace Drupal\projects\Form;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\Event\ProjectUnarchiveEvent;
use Drupal\projects\ProjectInterface;

/**
 * Lets administrators make an archived project active again.
 */
class ProjectUnarchiveForm extends ProjectEventFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_unarchive_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function label(): TranslatableMarkup {
    return $this->t('Unarchive Project');
  }

  /**
   * {@inheritdoc}
   */
  protected function successMessage(): TranslatableMarkup {
    return $this->t('Project was unarchived successfully.');
  }

  /**
   * {@inheritdoc}
   */
  protected function event(ProjectInterface $project, FormStateInterface $form_state): Event {
    return new ProjectUnarchiveEvent($project);
  }

}
