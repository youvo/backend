<?php

namespace Drupal\user_types\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Filters users by whether they came back after registering.
 *
 * A day after registering, as on the old site.
 */
#[ViewsFilter('user_returned')]
class UserReturned extends BooleanOperator {

  /**
   * The seconds after the registration from which an access is a return.
   */
  const int RETURN_AFTER = 86400;

  /**
   * Compares the last access with the registration.
   */
  public function query(): void {
    $this->ensureMyTable();
    $operator = empty($this->value) ? '<' : '>=';
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhereExpression(
      $this->options['group'],
      "$this->tableAlias.access $operator $this->tableAlias.created + :return_after",
      [':return_after' => static::RETURN_AFTER],
    );
  }

}
