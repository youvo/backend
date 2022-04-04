<?php

namespace Drupal\user_types\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for user update resources.
 */
class Access extends ControllerBase {

  /**
   * Checks access for change password REST resources.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function updatePassword(AccountInterface $account) {

    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Checks access for change email REST resources.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function updateEmail(AccountInterface $account) {

    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }
}
