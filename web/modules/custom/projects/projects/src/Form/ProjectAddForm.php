<?php

namespace Drupal\projects\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Entity form variant for adding projects.
 */
class ProjectAddForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project Title'),
      '#required' => TRUE,
    ];

    $form['uid'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_handler' => 'default:user',
      '#title' => $this->t('Organization'),
      '#required' => TRUE,
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'target_bundles' => ['organization'],
      ],
    ];

    $form['#process'][] = '::processForm';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Shape values to be consumed by content entity form.
    $form_state->setValue('uid', [0 => ['target_id' => $form_state->getValue('uid')]]);
    $form_state->setValue('title', [0 => ['value' => $form_state->getValue('title')]]);
    return parent::validateForm($form, $form_state);
  }

}
