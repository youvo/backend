<?php

namespace Drupal\creatives\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the creative user bundle.
 *
 * Every result carries its cache contexts. Without them, a creative's profile
 * could be cached with their private fields and shown to others.
 */
class CreativeFieldAccess extends FieldAccess {

  const PUBLIC_FIELDS = [
    'field_name',
    'field_avatar',
  ];

  /**
   * Contact fields: the creative and the managers see them.
   */
  const CONTACT_FIELDS = [
    'field_phone',
  ];

  /**
   * Private fields: only the creative sees them.
   */
  const PRIVATE_FIELDS = [
    'field_newsletter',
    'field_jobs',
    'field_public_profile',
  ];

  /**
   * {@inheritdoc}
   */
  public static function checkFieldAccess(
    ContentEntityInterface $entity,
    string $operation,
    FieldDefinitionInterface $field,
    AccountInterface $account,
  ): AccessResultInterface {

    // Only creative fields should be controlled by this class.
    if (!$entity instanceof Creative) {
      return AccessResult::forbidden();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer users')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Anonymous users can only access public fields.
    if ($account->isAnonymous()) {
      if ($operation === 'view' &&
        self::isFieldOfGroup($field, self::PUBLIC_FIELDS)
      ) {
        return AccessResult::neutral()->addCacheContexts(['user.roles:anonymous']);
      }
      return AccessResult::forbidden()->addCacheContexts(['user.roles:anonymous']);
    }

    // Managers may view contact fields, but only the creative edits them.
    if (self::isFieldOfGroup($field, self::CONTACT_FIELDS)) {
      if ($operation === 'view' && $account->hasPermission('general manager access')) {
        return AccessResult::neutral()->cachePerPermissions();
      }
      if ($entity->id() == $account->id()) {
        return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
      }
      return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
    }

    // Private fields are only accessible for the creative itself.
    if (self::isFieldOfGroup($field, self::PRIVATE_FIELDS)) {
      if ($entity->id() == $account->id()) {
        return AccessResult::neutral()->cachePerUser();
      }
      return AccessResult::forbidden()->cachePerUser();
    }

    return AccessResult::neutral()->addCacheContexts(['user.roles:anonymous']);
  }

}
