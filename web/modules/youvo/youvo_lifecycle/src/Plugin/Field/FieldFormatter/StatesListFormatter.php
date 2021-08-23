<?php

declare(strict_types = 1);

namespace Drupal\youvo_lifecycle\Plugin\Field\FieldFormatter;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\StateInterface;
use Drupal\youvo_lifecycle\Plugin\Field\FieldType\WorkflowsFieldItem;

/**
 * Plugin implementation of the 'youvo_lifecycle_state_list' formatter.
 *
 * @FieldFormatter(
 *   id = "youvo_lifecycle_state_list",
 *   label = @Translation("States list"),
 *   field_types = {
 *     "youvo_lifecycle_item"
 *   }
 * )
 */
class StatesListFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return array_map(function (WorkflowsFieldItem $item) {
      return [
        '#theme' => 'item_list__states_list',
        '#context' => ['list_style' => 'workflows-states-list'],
        '#attributes' => ['class' => [Html::cleanCssIdentifier($item->value) . '--active']],
        '#items' => $this->buildItems($item),
        '#cache' => ['max-age' => 0],
      ];
    }, iterator_to_array($items));
  }

  /**
   * Builds the items array for theme item list.
   *
   * @param \Drupal\youvo_lifecycle\Plugin\Field\FieldType\WorkflowsFieldItem $item
   *   The currently active workflow item.
   *
   * @return array
   *   An array of items for theme item_list.
   */
  protected function buildItems(WorkflowsFieldItem $item): array {
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
    $workflow = Workflow::load($this->getFieldSetting('workflow'));
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
        return (string) $state->label();
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
