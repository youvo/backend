<?php

namespace Drupal\prospects\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Access controller for prospects.
 */
class ProspectAccessController extends ControllerBase {

  /**
   * Checks access for prospect manage resource.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\user\UserInterface|null $organization
   *   The prospect.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProspectManage(AccountInterface $account, UserInterface $organization = NULL) {

    // Switch naming from convention in route.
    $prospect = $organization;

    // Return, if prospect is empty.
    if (!$prospect) {
      return AccessResult::neutral();
    }

    if ($prospect instanceof User) {
      return AccessResult::allowedIf(in_array('manager', $account->getRoles()));
    }

    return AccessResult::neutral();
  }

  /**
   * Checks access for prospect create resource.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProspectCreate(AccountInterface $account) {

    if ($account->isAnonymous()) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

}
