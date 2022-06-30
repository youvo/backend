<?php

namespace Drupal\creatives\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the creative user bundle.
 */
class CreativeFieldAccess extends FieldAccess {

  const PUBLIC_FIELDS = [
    'field_name',
    'field_avatar',
  ];

  const PRIVATE_FIELDS = [
    'field_newsletter',
    'field_jobs',
    'field_public_profile',
    'field_phone',
  ];

  /**
   * {@inheritdoc}
   */
  public static function checkFieldAccess(ContentEntityInterface $entity, string $operation, FieldDefinitionInterface $field, AccountInterface $account) {

    // Only project fields should be controlled by this class.
    if (!$entity instanceof Creative) {
      return AccessResult::forbidden();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer users')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Anonymous users can only access public fields.
    if ($account->isAnonymous()) {
      if ($operation == 'view' &&
        self::isFieldOfGroup($field, self::PUBLIC_FIELDS)) {
        return AccessResult::neutral();
      }
      else {
        return AccessResult::forbidden();
      }
    }

    // Private fields are only accessible for the creative itself.
    if ($entity->id() != $account->id() &&
      self::isFieldOfGroup($field, self::PRIVATE_FIELDS)) {
      return AccessResult::forbidden();
    }

    return AccessResult::neutral();
  }

}
