<?php

namespace Drupal\youvo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
    $settings->set('rest_prefix', $form_state->getValue('rest_prefix'));
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

    $form['rest_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('REST Prefix'),
      '#description' => $this->t('An obscurity prefix for the rest paths.'),
      '#default_value' => $config->get('rest_prefix'),
    ];

    return parent::buildForm($form, $form_state);
  }
}
