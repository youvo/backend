<?php

namespace Drupal\lifecycle\Plugin\Field\FieldFormatter;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem;
use Drupal\workflows\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'lifecycle_state_list' formatter.
 *
 * @FieldFormatter(
 *   id = "lifecycle_state_list",
 *   label = @Translation("States list"),
 *   field_types = {
 *     "lifecycle_item"
 *   }
 * )
 */
class StatesListFormatter extends FormatterBase {

  /**
   * The workflow storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected ConfigEntityStorage $workflowStorage;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorage $workflow_storage
   *   The workflow storage.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    ConfigEntityStorage $workflow_storage,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->workflowStorage = $workflow_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('workflow')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem $item */
    foreach (iterator_to_array($items) as $item) {
      $elements[] = [
        '#theme' => 'item_list__states_list',
        '#context' => ['list_style' => 'workflows-states-list'],
        '#attributes' => ['class' => [Html::cleanCssIdentifier($item->value) . '--active']],
        '#items' => $this->buildItems($item),
      ];
    }
    return $elements;
  }

  /**
   * Builds the items array for theme item list.
   *
   * @param \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem $item
   *   The currently active workflow item.
   *
   * @return array
   *   An array of items for theme item_list.
   */
  protected function buildItems(LifecycleItem $item): array {
    $states = $this->getStatesFromWorkflow();

    // Remove excluded states.
    $excludedStates = $this->getExcludedStates();
    $states = array_filter($states, function (string $stateId) use ($excludedStates): bool {
      return !in_array($stateId, $excludedStates, TRUE);
    }, \ARRAY_FILTER_USE_KEY);

    $beforeCurrent = TRUE;
    return array_map(function (StateInterface $state) use ($item, &$beforeCurrent): array {
      $isCurrent = $item->value === $state->id();

      // Once we've found the current item no longer mark the items as before
      // current. We only apply sibling classes when the item is not the current
      // item.
      if ($isCurrent) {
        $beforeCurrent = FALSE;
        $class = 'is-current';
      }
      else {
        $class = $beforeCurrent ? 'before-current' : 'after-current';
      }

      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $state->label(),
        '#wrapper_attributes' => [
          'class' => [
            $state->id(), $class,
          ],
        ],
      ];
    }, array_values($states));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'excluded_states' => [],
    ];
  }

  /**
   * Gets all available states from the workflow for this field.
   *
   * @return \Drupal\workflows\StateInterface[]
   *   An array of workflow states.
   */
  protected function getStatesFromWorkflow(): array {
    /** @var \Drupal\workflows\WorkflowInterface|null $workflow */
    $workflow = $this->workflowStorage->load($this->getFieldSetting('workflow'));
    $type = $workflow->getTypePlugin();
    $states = $type->getStates();
    assert(Inspector::assertAllObjects($states, StateInterface::class));
    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['excluded_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Excluded states'),
      '#options' => array_map(function (StateInterface $state): string {
        return $state->label();
      }, $this->getStatesFromWorkflow()),
      '#default_value' => $this->getSetting('excluded_states'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $excludedStates = $this->getExcludedStates();
    $summary[] = count($excludedStates) > 0
      ? $this->t('Excluded states: @states', ['@states' => implode(', ', $excludedStates)])
      : $this->t('Excluded states: n/a');
    return $summary;
  }

  /**
   * Get the states excluded from display.
   *
   * @return string[]
   *   An array of excluded state IDs.
   */
  protected function getExcludedStates(): array {
    return array_filter($this->getSetting('excluded_states'));
  }

}
