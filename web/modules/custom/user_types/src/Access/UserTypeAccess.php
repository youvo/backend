<?php

namespace Drupal\user_types\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access handler for user update resources.
 */
class UserTypeAccess {

  /**
   * Checks access for change password REST resources.
   */
  public function updatePassword(AccountInterface $account): AccessResultInterface {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

  /**
   * Checks access for change email REST resources.
   */
  public function updateEmail(AccountInterface $account): AccessResultInterface {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
