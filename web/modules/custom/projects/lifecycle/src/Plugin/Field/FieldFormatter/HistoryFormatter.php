<?php

namespace Drupal\lifecycle\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the lifecycle history, newest first: each state with its date.
 *
 * Each item is a term and its description, for a description list in the
 * template that prints the field.
 */
#[FieldFormatter(
  id: 'lifecycle_history',
  label: new TranslatableMarkup('History'),
  field_types: ['lifecycle_history_item'],
)]
class HistoryFormatter extends FormatterBase {

  /**
   * The entity type manager, to load the workflow.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'workflow' => '',
      'date_format' => 'd.m.Y',
      'oldest_first' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $workflows = $this->entityTypeManager->getStorage('workflow')->loadMultiple();
    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#description' => $this->t('The workflow that names the states.'),
      '#options' => array_map(static fn (WorkflowInterface $workflow): string => (string) $workflow->label(), $workflows),
      '#default_value' => $this->getSetting('workflow'),
      '#required' => TRUE,
    ];
    $form['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date format'),
      '#description' => $this->t('A PHP date format.'),
      '#default_value' => $this->getSetting('date_format'),
      '#required' => TRUE,
    ];
    $form['oldest_first'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Oldest first'),
      '#description' => $this->t('List the states in the order they were reached. Otherwise the current state comes first.'),
      '#default_value' => $this->getSetting('oldest_first'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Workflow: @workflow', ['@workflow' => $this->getSetting('workflow') ?: $this->t('None')]),
      $this->t('Date format: @format', ['@format' => $this->getSetting('date_format')]),
      $this->getSetting('oldest_first') ? $this->t('Oldest first') : $this->t('Current state first'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    $labels = [];
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($this->getSetting('workflow'));
    if ($workflow instanceof WorkflowInterface) {
      foreach ($workflow->getTypePlugin()->getStates() as $id => $state) {
        $labels[$id] = $state->label();
      }
    }

    $elements = [];
    $history = iterator_to_array($items);
    if (!$this->getSetting('oldest_first')) {
      $history = array_reverse($history);
    }
    foreach ($history as $item) {
      $state = (string) $item->get('to')->getValue();
      $elements[] = [
        '#type' => 'inline_template',
        '#template' => '<dt>{{ state }}</dt><dd>{{ date }}</dd>',
        '#context' => [
          'state' => $labels[$state] ?? $state,
          'date' => $this->dateFormatter->format((int) $item->get('timestamp')->getValue(), 'custom', $this->getSetting('date_format')),
        ],
      ];
    }
    if ($workflow instanceof WorkflowInterface) {
      CacheableMetadata::createFromObject($workflow)->applyTo($elements);
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($this->getSetting('workflow'));
    if ($workflow instanceof WorkflowInterface) {
      $dependencies[$workflow->getConfigDependencyKey()][] = $workflow->getConfigDependencyName();
    }
    return $dependencies;
  }

}
