<?php

namespace Drupal\user_types\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;

/**
 * Access controller for lecture rest resources.
 */
class Access extends ControllerBase {

  /**
   * Checks access for change password REST resources.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function updatePassword(AccountProxyInterface $account) {

    // Forbidden if anonymous or blocked.
    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }

    // Forbidden for blocked users.
    $account_entity = $account->getAccount();
    if (!$account_entity instanceof User ||
      $account_entity->isBlocked()) {
      return AccessResult::forbidden();
    }

    // Gather properties.
    return AccessResult::allowed();
  }
}
