<?php

namespace Drupal\lifecycle\Plugin\Field\FieldFormatter;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;
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
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
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
    $states = array_filter($states, static function (string $state_id) use ($excludedStates): bool {
      return !in_array($state_id, $excludedStates, TRUE);
    }, \ARRAY_FILTER_USE_KEY);

    $beforeCurrent = TRUE;
    return array_map(static function (StateInterface $state) use ($item, &$beforeCurrent): array {
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
    return ['excluded_states' => []];
  }

  /**
   * Gets all available states from the workflow for this field.
   *
   * @return \Drupal\workflows\StateInterface[]
   *   An array of workflow states.
   */
  protected function getStatesFromWorkflow(): array {
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($this->getFieldSetting('workflow'));
    if (!$workflow instanceof WorkflowInterface) {
      return [];
    }
    $type = $workflow->getTypePlugin();
    $states = $type->getStates();
    assert(Inspector::assertAllObjects($states, StateInterface::class));
    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements['excluded_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Excluded states'),
      '#options' => array_map(static function (StateInterface $state): string {
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
