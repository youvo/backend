<?php

namespace Drupal\organizations\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Access controller for transition forms.
 */
class OrganizationAccessController extends ControllerBase {

  /**
   * Checks access for project apply.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\user\UserInterface|null $organization
   *   The node id.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessOrganizationManage(AccountInterface $account, UserInterface $organization = NULL) {

    // Return, if organizations is empty.
    if (!$organization) {
      return AccessResult::neutral();
    }

    if ($organization instanceof User) {
      return AccessResult::allowedIf(in_array('manager', $account->getRoles()));
    }

    return AccessResult::neutral();
  }

  /**
   * Checks access for organization create resource.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessCreate(AccountInterface $account) {

    // Only anonymous user is allowed to create new organization.
    if ($account->isAnonymous()) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

}
