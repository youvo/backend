<?php

namespace Drupal\oauth_grant_remote\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_oauth\Service\Filesystem\FileSystemChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form.
 *
 * @internal
 */
class OauthGrantRemoteSettingsForm extends ConfigFormBase {

  /**
   * The file system checker.
   */
  protected FileSystemChecker $fileSystemChecker;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->fileSystemChecker = $container->get('simple_oauth.filesystem_checker');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oauth_grant_remote_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['oauth_grant_remote.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->config('oauth_grant_remote.settings');

    $form['jwt_expiration'] = [
      '#type' => 'number',
      '#title' => $this->t('JWT Token Expiration Time'),
      '#description' => $this->t('The default period in seconds while a JWT token is valid.'),
      '#default_value' => $config->get('jwt_expiration'),
      '#required' => TRUE,
    ];
    $form['jwt_key_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JWT Key Path'),
      '#description' => $this->t('The path to the key file.'),
      '#default_value' => $config->get('jwt_key_path'),
      '#element_validate' => ['::validateExistingFile'],
      '#required' => TRUE,
    ];
    $form['auth_relay_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Relay URL'),
      '#description' => $this->t('The URL of the Auth Relay.'),
      '#default_value' => $config->get('auth_relay_url'),
      '#required' => TRUE,
    ];
    $form['development'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Development Environment?'),
      '#default_value' => $config->get('development'),
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

  /**
   * Validates if the file exists.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateExistingFile(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    if (!empty($element['#value'])) {
      $path = $element['#value'];
      // Does the file exist?
      if (!$this->fileSystemChecker->fileExist($path)) {
        $form_state->setError($element, $this->t('The %field file does not exist.', ['%field' => $element['#title']]));
      }
      // Is the file readable?
      if (!$this->fileSystemChecker->isReadable($path)) {
        $form_state->setError($element, $this->t('The %field file at the specified location is not readable.', ['%field' => $element['#title']]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $settings = $this->config('oauth_grant_remote.settings');
    $settings->set('jwt_expiration', $form_state->getValue('jwt_expiration'));
    $settings->set('jwt_key_path', $form_state->getValue('jwt_key_path'));
    $settings->set('auth_relay_url', $form_state->getValue('auth_relay_url'));
    $settings->set('development', $form_state->getValue('development'));
    $settings->save();
    parent::submitForm($form, $form_state);
  }

}
