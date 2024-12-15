<?php

namespace Drupal\blocker_mode\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form.
 *
 * @internal
 */
class BlockerModeSettingsForm extends ConfigFormBase {

  /**
   * The state key-value collection.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'blocker_mode_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['blocker_mode.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->state->set('system.blocker_mode', $form_state->getValue('blocker_mode'));
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['blocker_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Blocker Mode Enabled'),
      '#description' => $this->t('Switch to enable blocker mode.'),
      '#default_value' => $this->state->get('system.blocker_mode'),
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
