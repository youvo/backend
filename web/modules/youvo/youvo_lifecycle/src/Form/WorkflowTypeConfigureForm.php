<?php

declare(strict_types = 1);

namespace Drupal\youvo_lifecycle\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Plugin\WorkflowTypeConfigureFormBase;
use Drupal\workflows\State;

/**
 * Plugin configuration form for the "Youvo Lifecycle" workflow type.
 */
class WorkflowTypeConfigureForm extends WorkflowTypeConfigureFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->workflowType->getConfiguration();
    $form['settings'] = [
      '#title' => $this->t('Workflow Settings'),
      '#type' => 'fieldset',
    ];
    $labels = array_map([State::class, 'labelCallback'], $this->workflowType->getStates());
    $form['settings']['initial_state'] = [
      '#title' => $this->t('Initial State'),
      '#type' => 'select',
      '#default_value' => $configuration['initial_state'] ?? NULL,
      '#options' => $labels,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $configuration = $this->workflowType->getConfiguration();
    $configuration['initial_state'] = $form_state->getValue(['settings', 'initial_state']);
    $this->workflowType->setConfiguration($configuration);
  }

}
