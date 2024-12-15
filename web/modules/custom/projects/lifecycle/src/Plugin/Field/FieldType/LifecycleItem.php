<?php

declare(strict_types=1);

namespace Drupal\lifecycle\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\lifecycle\Permissions;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Workflow state field item.
 *
 * @FieldType(
 *   id = "lifecycle_item",
 *   label = @Translation("Workflows"),
 *   description = @Translation("Allows you to store a workflow state."),
 *   constraints = {"LifecycleConstraint" = {}},
 *   default_formatter = "lifecycle_state_list",
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
      ->setLabel('State')
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
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    $options = array_map(static function (WorkflowInterface $workflow): string {
      return (string) $workflow->label();
    }, Workflow::loadMultipleByType('lifecycle'));

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
  public function getPossibleValues(?AccountInterface $account = NULL): array {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL): array {
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      // The workflow is not known yet, the field is probably being created.
      return [];
    }

    return array_map(static function (StateInterface $state): string {
      return $state->label();
    }, $workflow->getTypePlugin()->getStates());
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getSettableValues(?AccountInterface $account = NULL): array {
    return array_keys($this->getSettableOptions($account));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getSettableOptions(?AccountInterface $account = NULL): array {
    // $this->value is unpopulated due to https://www.drupal.org/node/2629932
    $fieldName = $this->getFieldDefinition()->getName();
    $item = $this->getEntity()->get($fieldName)->first();
    assert($item instanceof static);

    $workflow = $this->getWorkflow();
    if (!$workflow instanceof WorkflowInterface) {
      return [];
    }

    $type = $workflow->getTypePlugin();
    $current_state_id = $item->value;
    /** @var \Drupal\workflows\StateInterface|null $current_state */
    $current_state = ($current_state_id && $type->hasState($current_state_id)) ? $type->getState($current_state_id) : NULL;
    $states = $type->getStates();
    if ($current_state) {
      // If the current state is valid, then filter out undesirable states:
      $states = array_filter($states, static function (StateInterface $state) use ($current_state, $workflow, $account): bool {
        // Always include the current state as a possible option.
        if ($current_state->id() === $state->id()) {
          return TRUE;
        }

        // If we don't have a valid transition, or we don't have an account then
        // all we care about is whether the transition is valid so return.
        $valid_transition = $current_state->canTransitionTo($state->id());
        if (!$valid_transition || !$account) {
          return $valid_transition;
        }

        // If we have an account object then ensure the user has permission to
        // this transition and that it's a valid transition.
        $transition = $current_state->getTransitionTo($state->id());
        return Permissions::useTransition($account, $workflow->id(), $transition);
      });
    }

    return array_map(static function ($state): string {
      return (string) $state->label();
    }, $states);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function applyDefaultValue($notify = TRUE): static {
    if ($workflow = $this->getWorkflow()) {
      $initial_state = $workflow->getTypePlugin()->getInitialState();
      $this->setValue(['value' => $initial_state->id()], $notify);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateStorageDependencies(FieldStorageDefinitionInterface $field_definition): array {
    $dependencies['config'][] = sprintf('workflows.workflow.%s', $field_definition->getSetting('workflow'));
    return $dependencies;
  }

  /**
   * Gets the workflow associated with this field.
   */
  public function getWorkflow(): ?WorkflowInterface {
    return !empty($this->getSetting('workflow')) ? Workflow::load($this->getSetting('workflow')) : NULL;
  }

}
