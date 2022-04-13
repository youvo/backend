<?php

namespace Drupal\creatives;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;

/**
 * Access controller for the Creative entity.
 */
class CreativeAccessControlHandler {

  /**
   * Checks access.
   *
   * @see \Drupal\user_types\UserTypeAccessControlHandler::checkAccess()
   */
  public static function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Only organizations should be handled by this handler.
    if (!$entity instanceof Creative) {
      return AccessResult::neutral();
    }

    return AccessResult::neutral();
  }

  /**
   * Checks create access.
   *
   * @todo Rework when registration for creatives lands.
   *
   * @see \Drupal\user_types\UserTypeAccessControlHandler::checkCreateAccess()
   */
  public static function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if (in_array('administrator', $account->getRoles())) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden();
  }

}
