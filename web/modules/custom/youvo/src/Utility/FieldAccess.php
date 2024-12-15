<?php

namespace Drupal\youvo\Utility;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides field access methods for the project bundle.
 */
abstract class FieldAccess {

  /**
   * Static call for the hook to check field access.
   *
   * See hook_entity_field_access().
   */
  abstract public static function checkFieldAccess(
    ContentEntityInterface $entity,
    string $operation,
    FieldDefinitionInterface $field,
    AccountInterface $account,
  ): AccessResultInterface;

  /**
   * Determines if a field is part of a defined group.
   */
  public static function isFieldOfGroup(FieldDefinitionInterface|string $field, array $group): bool {
    return in_array(static::getFieldName($field), $group, TRUE);
  }

  /**
   * Gets the field name.
   */
  protected static function getFieldName(FieldDefinitionInterface|string $field): string {
    return $field instanceof FieldDefinitionInterface ? $field->getName() : $field;
  }

}
