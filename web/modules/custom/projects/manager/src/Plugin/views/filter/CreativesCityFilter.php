<?php

namespace Drupal\manager\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Filters the creatives by city, where no city never contains a text.
 *
 * The core "does not contain" writes NOT LIKE, which drops the creatives
 * without a city: their joined column is NULL. A creative without a city
 * doesn't live in the city that was searched for, so it stays in the list.
 */
#[ViewsFilter('creatives_city')]
class CreativesCityFilter extends StringFilter {

  /**
   * Adds the "does not contain" condition, which keeps an empty city.
   *
   * @param string $field
   *   The aliased field to filter on.
   */
  protected function opNotLike($field): void {
    $condition = $this->connection->condition('OR')
      ->isNull($field)
      ->condition($field, '%' . $this->connection->escapeLike($this->value) . '%', $this->getConditionOperator('NOT LIKE'));
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $condition);
  }

}
