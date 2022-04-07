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

  /**
   * With different authorization methods the account object may be a
   * AccountProxy or a TokenAuthUser. Use this helper to determine whether
   * the account is of a specific user type.
   */
  protected static function isUserType(
    AccountInterface $account,
    string $type,
    string $class
  ) {
    if ($account instanceof AccountProxyInterface) {
      $account = $account->getAccount();
      if (class_exists('Drupal\\simple_oauth\\Authentication\\TokenAuthUser') &&
        $account instanceof \Drupal\simple_oauth\Authentication\TokenAuthUser) {
        return $account->bundle() == $type;
      }
      return $account instanceof $class;
    }
    return $account instanceof $class;
  }

  /**
   * Helper to determine if account is creative.
   */
  protected static function isCreative(AccountInterface $account) {
    return self::isUserType($account, 'user', 'Creative');
  }

  /**
   * Helper to determine if account is organization.
   */
  protected static function isOrganization(AccountInterface $account) {
    return self::isUserType($account, 'organization', 'Organization');
  }

}
