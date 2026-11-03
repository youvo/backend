<?php

namespace Drupal\logbook\Plugin\views;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

/**
 * Matches the logs of people, organizations and projects.
 *
 * Shared by the logbook search and contextual filters, so both match the same
 * logs. The plugin provides entityTypeManager, entityFieldManager, database
 * and table.
 */
trait LogMatchTrait {

  /**
   * The fields of the log that hold the people and organizations in it.
   */
  protected const array PARTICIPANTS = ['author', 'manager', 'organization', 'creatives'];

  /**
   * Matches the logs of any of the people, organizations and projects.
   *
   * @param string $alias
   *   The alias of the log table.
   * @param int[] $users
   *   The ids of the people and organizations.
   * @param int[] $projects
   *   The ids of the projects.
   */
  protected function entityCondition(string $alias, array $users, array $projects): Condition {
    $or = new Condition('OR');
    if ($users) {
      foreach (static::PARTICIPANTS as $field) {
        $this->logCondition($or, $alias, $field, $users, 'IN');
      }
      // Feedback logs carry no organization, only the project.
      $this->logCondition($or, $alias, 'project', $this->ids('project', 'uid', $users, 'IN'), 'IN');
    }
    if ($projects) {
      $this->logCondition($or, $alias, 'project', $projects, 'IN');
    }
    return $or;
  }

  /**
   * Adds a condition on a field of the log.
   */
  protected function logCondition(Condition $condition, string $alias, string $field, mixed $value, string $operator): void {
    [$table, $column] = $this->column('log', $field);
    if ($table === $this->table) {
      $condition->condition("$alias.$column", $value, $operator);
      return;
    }
    $condition->condition("$alias.id", $this->ids('log', $field, $value, $operator), 'IN');
  }

  /**
   * Selects the ids of the entities whose field matches.
   */
  protected function ids(string $entity_type_id, string $field, mixed $value, string $operator): SelectInterface {
    [$table, $column, $id] = $this->column($entity_type_id, $field);
    return $this->database->select($table, 't')
      ->fields('t', [$id])
      ->condition("t.$column", $value, $operator);
  }

  /**
   * Gets where a field of an entity type is stored.
   *
   * @return string[]
   *   The table, the column of the main property and the column of the
   *   entity id in that table.
   */
  protected function column(string $entity_type_id, string $field): array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    assert($storage instanceof SqlEntityStorageInterface);
    $mapping = $storage->getTableMapping();
    assert($mapping instanceof DefaultTableMapping);
    $definition = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id)[$field];
    $id = $mapping->requiresDedicatedTableStorage($definition)
      ? 'entity_id'
      : (string) $storage->getEntityType()->getKey('id');
    return [
      $mapping->getFieldTableName($field),
      $mapping->getFieldColumnName($definition, (string) $definition->getMainPropertyName()),
      $id,
    ];
  }

}
