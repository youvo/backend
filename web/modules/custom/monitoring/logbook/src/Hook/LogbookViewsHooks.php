<?php

namespace Drupal\logbook\Hook;

use Drupal\Core\Form\FormStateInterface;
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
   * Adds the fields and the pattern filter of the logbook view to the log
   * table. They have no column of their own.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['views']['logbook_empty'] = [
      'title' => $this->t('Logbook: Empty'),
      'help' => $this->t('Says why the logbook is empty.'),
      'area' => ['id' => 'logbook_empty'],
    ];
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
    $data['log']['log_pattern_scope'] = [
      'title' => $this->t('Logbook: Listed patterns'),
      'help' => $this->t('Limits the logs to the enabled, observable log patterns.'),
      'filter' => ['id' => 'log_pattern_scope', 'real field' => 'type'],
    ];
    $data['log']['log_involves'] = [
      'title' => $this->t('Logbook: Involves'),
      'help' => $this->t('Limits the logs to the people, organizations, projects and texts of the search bar.'),
      'filter' => ['id' => 'log_involves', 'real field' => 'id'],
    ];
    $data['log']['log_visibility'] = [
      'title' => $this->t('Logbook: Visibility'),
      'help' => $this->t('Icons for who can see the log.'),
      'field' => ['id' => 'log_visibility'],
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

  /**
   * Implements hook_form_FORM_ID_alter() for the views exposed form.
   *
   * The date filter of Views shows two plain text fields. The browser's date
   * input is the better widget and still submits Y-m-d, which the filter
   * parses. The pattern select says what "all" means instead of "- Any -".
   */
  #[Hook('form_views_exposed_form_alter')]
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $view = $form_state->get('view');
    if (!$view instanceof ViewExecutable || $view->id() !== 'logbook') {
      return;
    }
    // The filter puts the two fields in a wrapper fieldset.
    if (isset($form['created_wrapper']['created'])) {
      $labels = ['min' => $this->t('From'), 'max' => $this->t('To')];
      foreach ($labels as $key => $label) {
        if (isset($form['created_wrapper']['created'][$key])) {
          $element = &$form['created_wrapper']['created'][$key];
          $element['#type'] = 'date';
          $element['#title'] = $label;
          unset($element['#size'], $element['#maxlength'], $element);
        }
      }
    }
    $form['#validate'][] = [static::class, 'includeEndDay'];
    if (isset($form['type']['#options']['All'])) {
      $form['type']['#options']['All'] = $this->t('All types');
    }
  }

  /**
   * Validate callback of the exposed form: the end day belongs to the range.
   *
   * The date filter turns a date without a time into midnight, which would
   * leave out the whole end day. The value of the form is changed, not the
   * input, so the date field keeps showing the day that was picked.
   */
  public static function includeEndDay(array &$form, FormStateInterface $form_state): void {
    $end = $form_state->getValue(['created', 'max']);
    if (is_string($end) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
      $form_state->setValue(['created', 'max'], $end . ' 23:59:59');
    }
  }

}
