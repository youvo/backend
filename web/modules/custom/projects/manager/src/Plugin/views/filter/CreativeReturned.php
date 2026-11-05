<?php

namespace Drupal\manager\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Filters the creatives by whether they came back after registering.
 *
 * A creative returned when the last access lies at least a day after the
 * registration, as on the old site. Creatives who never logged in did not.
 */
#[ViewsFilter('creative_returned')]
class CreativeReturned extends BooleanOperator {

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
