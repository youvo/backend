<?php

namespace Drupal\stats\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stats\StatsPeriod;

/**
 * Provides the date range form of the stats page.
 *
 * Submitting it shows the stats of a custom range from one day to another,
 * both inclusive.
 */
class StatsRangeForm extends FormBase {

  /**
   * Gets the form ID.
   */
  public function getFormId(): string {
    return 'stats_range_form';
  }

  /**
   * Builds the form with the inclusive days of the current period.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $from
   *   The first day of the current period, Y-m-d.
   * @param string $to
   *   The last day of the current period, Y-m-d.
   * @param string $today
   *   The current day, Y-m-d, the latest day to pick.
   *
   * @return array
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $from = '', string $to = '', string $today = ''): array {
    $form['#attributes']['class'][] = 'stats-range-form';
    // The days come from the period, whose query arguments the stats page
    // varies by. Opting in to caching keeps the page cacheable; the form token
    // is a placeholder anyway.
    $form['#cache']['max-age'] = Cache::PERMANENT;
    $form_state->set('today', $today);
    // The days run from the start of the old Logbuch to today.
    $limits = ['min' => StatsPeriod::ALL_START, 'max' => $today];
    $form['from'] = [
      '#type' => 'date',
      '#title' => $this->t('From'),
      '#default_value' => $from,
      '#attributes' => $limits,
      // validateForm() asks for missing days, without the required markers.
      '#required' => FALSE,
    ];
    $form['to'] = [
      '#type' => 'date',
      '#title' => $this->t('To'),
      '#default_value' => $to,
      '#attributes' => $limits,
      '#required' => FALSE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * Validates the days: both exist, lie from 2014 to today, in order.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

    $from_value = $form_state->getValue('from');
    $to_value = $form_state->getValue('to');
    if ($from_value === '' || $from_value === NULL) {
      $form_state->setErrorByName('from', $this->t('Enter a start date.'));
    }
    if ($to_value === '' || $to_value === NULL) {
      $form_state->setErrorByName('to', $this->t('Enter an end date.'));
    }
    if ($form_state->getErrors()) {
      return;
    }

    // The same parser as the query, so the form accepts what the page shows.
    $timezone = new \DateTimeZone('UTC');
    $from = StatsPeriod::parseDate($from_value, $timezone);
    $to = StatsPeriod::parseDate($to_value, $timezone);
    if ($from === NULL) {
      $form_state->setErrorByName('from', $this->t('Enter a valid start date.'));
    }
    if ($to === NULL) {
      $form_state->setErrorByName('to', $this->t('Enter a valid end date.'));
    }

    if ($from !== NULL && $from_value < StatsPeriod::ALL_START) {
      $form_state->setErrorByName('from', $this->t('The start date must not be before 01.01.2014, as there is no data before.'));
    }
    $today = $form_state->get('today');
    if ($to !== NULL && $today && $to_value > $today) {
      $form_state->setErrorByName('to', $this->t('The end date must not be after today.'));
    }
    if ($from !== NULL && $to !== NULL && $to < $from) {
      $form_state->setErrorByName('to', $this->t('The end date must not be before the start date.'));
    }
  }

  /**
   * Redirects to the stats page with the custom range.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('stats.overview', [], [
      'query' => [
        'period' => StatsPeriod::CUSTOM,
        'from' => $form_state->getValue('from'),
        'to' => $form_state->getValue('to'),
      ],
    ]);
  }

}
