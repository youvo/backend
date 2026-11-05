<?php

namespace Drupal\user_types\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\Date;

/**
 * Filters a timestamp of users by a range of days.
 *
 * Unlike the core date filter, either end of the range may stay open, and the
 * range includes the whole last day. Dates that cannot be read are ignored.
 */
#[ViewsFilter('user_date_range')]
class UserDateRange extends Date {

  /**
   * Accepts a range with only one end as well.
   *
   * @param array $input
   *   The exposed input.
   *
   * @return bool
   *   Whether the filter applies.
   */
  public function acceptExposedInput($input): bool {
    $accepted = (bool) parent::acceptExposedInput($input);
    if (!$accepted && !empty($this->options['exposed']) && $this->operator === 'between') {
      return ($this->value['min'] ?? '') !== '' || ($this->value['max'] ?? '') !== '';
    }
    return $accepted;
  }

  /**
   * Limits the timestamp to the days from the start to the end.
   *
   * @param mixed $field
   *   The field to filter, as "table.column".
   */
  protected function opBetween($field): void {

    $start = ($this->value['min'] ?? '') !== '' ? strtotime($this->value['min'] . ' 00:00:00') : FALSE;
    $end = ($this->value['max'] ?? '') !== '' ? strtotime($this->value['max'] . ' 00:00:00 +1 day') : FALSE;

    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    if ($start !== FALSE) {
      $query->addWhereExpression($this->options['group'], "$field >= :start", [':start' => $start]);
    }
    if ($end !== FALSE) {
      $query->addWhereExpression($this->options['group'], "$field < :end", [':end' => $end]);
    }
  }

}
