<?php

namespace Drupal\youvo\Utility;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides field access methods for the project bundle.
 */
abstract class FieldAccess {

  /**
   * Static call for the hook to check field access.
   * @see hook_entity_field_access()
   */
  abstract public static function checkFieldAccess(
    ContentEntityInterface $entity,
    string $operation,
    FieldDefinitionInterface $field,
    AccountInterface $account
  );

  /**
   * Determines if a field is part of a defined group.
   */
  public static function isFieldOfGroup(FieldDefinitionInterface|string $field, array $group) {
    return in_array(static::getFieldName($field), $group);
  }

  /**
   * Helper function to fetch field name.
   */
  protected static function getFieldName(FieldDefinitionInterface|string $field) {
    return $field instanceof FieldDefinitionInterface ?
      $field->getName() : $field;
  }

}
