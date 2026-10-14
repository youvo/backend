<?php

namespace Drupal\logbook\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\logbook\LogInterface;
use Drupal\views\ViewExecutable;

/**
 * Views hook implementations of the logbook module.
 */
class LogbookViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   *
   * Adds the fields and filters of the logbook view to the log table. They
   * have no column of their own: the fields read the entity, the filters
   * resolve log patterns and restrict the log type.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    if (!isset($data['log'])) {
      return;
    }
    $data['log']['log_type'] = [
      'title' => $this->t('Logbook: Pattern'),
      'help' => $this->t('The log pattern as a pill.'),
      'field' => ['id' => 'log_type'],
    ];
    $data['log']['log_event_text'] = [
      'title' => $this->t('Logbook: Event text'),
      'help' => $this->t('The text of the log with its message.'),
      'field' => ['id' => 'log_event_text'],
    ];
    $data['log']['log_visibility'] = [
      'title' => $this->t('Logbook: Visibility'),
      'help' => $this->t('Icons for who can see the log.'),
      'field' => ['id' => 'log_visibility'],
    ];
    $data['log']['log_pattern_scope'] = [
      'title' => $this->t('Logbook: Observable patterns'),
      'help' => $this->t('Only enabled, observable log patterns.'),
      'filter' => ['id' => 'log_pattern_scope', 'real field' => 'type'],
    ];
    $data['log']['log_pattern_hidden'] = [
      'title' => $this->t('Logbook: Hidden patterns'),
      'help' => $this->t('Excludes hidden log patterns unless shown.'),
      'filter' => ['id' => 'log_pattern_hidden', 'real field' => 'type'],
    ];
    $data['log']['log_pattern_group'] = [
      'title' => $this->t('Logbook: Pattern group'),
      'help' => $this->t('Filter by the group of the log pattern.'),
      'filter' => ['id' => 'log_pattern_group', 'real field' => 'type'],
    ];
    $data['log']['log_pattern_visibility'] = [
      'title' => $this->t('Logbook: Pattern visibility'),
      'help' => $this->t('Filter by who can see the log.'),
      'filter' => ['id' => 'log_pattern_visibility', 'real field' => 'type'],
    ];
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->id() === 'logbook') {
      $view->element['#attached']['library'][] = 'logbook/table';
    }
  }

  /**
   * Implements hook_preprocess_views_view_table().
   *
   * A row of a celebrate pattern gets a class, as the manager adds status
   * classes to its rows.
   */
  #[Hook('preprocess_views_view_table')]
  public function preprocessViewsViewTable(array &$variables): void {
    if ($variables['view']->id() !== 'logbook') {
      return;
    }
    foreach ($variables['rows'] as $key => &$row) {
      $log = $variables['view']->result[$key]->_entity ?? NULL;
      if ($log instanceof LogInterface && $log->getPattern()->getTone() === 'celebrate') {
        $row['attributes']->addClass('logbook-row--celebrate');
      }
    }
  }

}
