<?php

namespace Drupal\youvo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides settings form for youvo base module.
 */
class YouvoSettingsForm extends ConfigFormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'youvo_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['youvo.settings'];
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->config('youvo.settings');
    $settings->set('api_prefix', $form_state->getValue('api_prefix'));
    $settings->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Defines the settings form for youvo.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('youvo.settings');

    $form['api_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Prefix'),
      '#description' => $this->t('An obscurity prefix for API paths.'),
      '#default_value' => $config->get('api_prefix'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
