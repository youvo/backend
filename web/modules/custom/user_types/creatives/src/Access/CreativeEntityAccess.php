<?php

namespace Drupal\creatives\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;

/**
 * Access controller for the Creative entity.
 */
class CreativeEntityAccess {

  /**
   * Checks access.
   *
   * @see \Drupal\user_types\UserTypeAccessControlHandler::checkAccess()
   */
  public static function checkAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    // Only creatives should be handled by this check.
    if (!$entity instanceof Creative) {
      return AccessResult::neutral();
    }
    return AccessResult::allowed();
  }

  /**
   * Checks access for creative create resource.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessCreate(AccountInterface $account): AccessResultInterface {
    // Only anonymous user is allowed to create new creative.
    if ($account->isAnonymous()) {
      return AccessResult::allowed();
    }
    return AccessResult::neutral();
  }

}
