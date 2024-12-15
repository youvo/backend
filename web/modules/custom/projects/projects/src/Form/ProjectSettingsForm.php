<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Configuration form for a project entity type.
 */
class ProjectSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_project_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['project_settings']['#markup'] = Link::fromTextAndUrl($this->t('Field settings'), Url::fromRoute('entity.project.field_ui_fields'))->toString();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Do nothing.
  }

}
