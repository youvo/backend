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

    return AccessResult::allowed();
  }

}
