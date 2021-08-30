<?php

declare(strict_types = 1);

namespace Drupal\youvo_lifecycle\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\youvo_lifecycle\Permissions;

/**
 * Workflow state field item.
 *
 * @FieldType(
 *   id = "youvo_lifecycle_item",
 *   label = @Translation("Workflows"),
 *   description = @Translation("Allows you to store a workflow state."),
 *   constraints = {"LifecycleConstraint" = {}},
 *   default_formatter = "list_default",
 *   default_widget = "options_select"
 * )
 *
 * @property string|null $value
 */
class LifecycleItem extends FieldItemBase implements OptionsProviderInterface {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('State'))
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'type' => 'varchar',
          'length' => 64,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    $settings = [
      'workflow' => NULL,
    ];
    return $settings + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $options = array_map(function (WorkflowInterface $workflow): string {
      return (string) $workflow->label();
    }, Workflow::loadMultipleByType('youvo_lifecycle'));

    $element = [];
    $element['workflow'] = [
      '#title' => $this->t('Workflow'),
      '#required' => TRUE,
      '#default_value' => $this->getSetting('workflow'),
      '#type' => 'select',
      '#options' => $options,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL): array {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(AccountInterface $account = NULL): array {
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      // The workflow is not known yet, the field is probably being created.
      return [];
    }

    return array_map(function (StateInterface $state): string {
      return $state->label();
    }, $workflow->getTypePlugin()->getStates());
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(AccountInterface $account = NULL): array {
    return array_keys($this->getSettableOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    // $this->value is unpopulated due to https://www.drupal.org/node/2629932
    $fieldName = $this->getFieldDefinition()->getName();
    $item = $this->getEntity()->get($fieldName)->first();
    assert($item instanceof static);

    $workflow = $this->getWorkflow();
    $type = $workflow->getTypePlugin();
    $currentStateId = $item->value;
    /** @var \Drupal\workflows\StateInterface|null $currentState */
    $currentState = ($currentStateId && $type->hasState($currentStateId)) ? $type->getState($currentStateId) : NULL;
    $states = $type->getStates();
    if ($currentState) {
      // If the current state is valid, then filter out undesirable states:
      $states = array_filter($states, function (StateInterface $state) use ($currentState, $workflow, $account): bool {
        // Always include the current state as a possible option.
        if ($currentState->id() === $state->id()) {
          return TRUE;
        }

        // If we don't have a valid transition, or we don't have an account then
        // all we care about is whether the transition is valid so return.
        $validTransition = $currentState->canTransitionTo($state->id());
        if (!$validTransition || !$account) {
          return $validTransition;
        }

        // If we have an account object then ensure the user has permission to
        // this transition and that it's a valid transition.
        $transition = $currentState->getTransitionTo($state->id());
        return Permissions::useTransition($account, $workflow->id(), $transition);
      });
    }

    return array_map(function ($state): string {
      return (string) $state->label();
    }, $states);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function applyDefaultValue($notify = TRUE) {
    if ($workflow = $this->getWorkflow()) {
      $initial_state = $workflow->getTypePlugin()->getInitialState();
      $this->setValue(['value' => $initial_state->id()], $notify);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateStorageDependencies(FieldStorageDefinitionInterface $field_definition) {
    $dependencies['config'][] = sprintf('workflows.workflow.%s', $field_definition->getSetting('workflow'));
    return $dependencies;
  }

  /**
   * Gets the workflow associated with this field.
   *
   * @return \Drupal\workflows\WorkflowInterface|null
   *   The workflow of NULL.
   */
  public function getWorkflow() {
    return !empty($this->getSetting('workflow')) ? Workflow::load($this->getSetting('workflow')) : NULL;
  }

}
