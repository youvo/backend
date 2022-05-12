<?php

namespace Drupal\oauth_grant\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The settings form.
 *
 * @internal
 */
class OauthGrantSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oauth_grant_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oauth_grant.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->config('oauth_grant.settings');
    $settings->set('auth_success_redirect', $form_state->getValue('auth_success_redirect'));
    $settings->set('auth_failure_redirect', $form_state->getValue('auth_failure_redirect'));
    $settings->set('local', $form_state->getValue('local'));
    $settings->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('oauth_grant.settings');

    $form['auth_success_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Success Redirect'),
      '#description' => $this->t('Frontend redirect after successful authentication.'),
      '#default_value' => $config->get('auth_success_redirect'),
      '#required' => TRUE,
    ];

    $form['auth_failure_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Failure Redirect'),
      '#description' => $this->t('Frontend redirect after failed authentication.'),
      '#default_value' => $config->get('auth_failure_redirect'),
      '#required' => TRUE,
    ];

    $form['local'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Local Redirects?'),
      '#default_value' => $config->get('local'),
      '#required' => FALSE,
    ];

    $form['actions'] = [
      'actions' => [
        '#cache' => ['max-age' => 0],
        '#weight' => 20,
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

}
