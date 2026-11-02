<?php

namespace Drupal\projects\Form;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectInterface;

/**
 * Lets administrators publish a pending project.
 */
class ProjectPublishForm extends ProjectEventFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_publish_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function label(): TranslatableMarkup {
    return $this->t('Publish Project');
  }

  /**
   * {@inheritdoc}
   */
  protected function successMessage(): TranslatableMarkup {
    return $this->t('Project was published successfully.');
  }

  /**
   * {@inheritdoc}
   */
  protected function event(ProjectInterface $project, FormStateInterface $form_state): Event {
    return new ProjectPublishEvent($project);
  }

}
