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
   * Adds the fields of the logbook view to the log table. They have no column
   * of their own: they read the entity.
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
    if (isset($form['type']['#options']['All'])) {
      $form['type']['#options']['All'] = $this->t('All types');
    }
  }

}
