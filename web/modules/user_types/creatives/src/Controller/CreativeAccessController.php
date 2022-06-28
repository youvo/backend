<?php

namespace Drupal\creatives\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for user create resources.
 */
class CreativeAccessController extends ControllerBase {

  /**
   * Checks access for creative create resource.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessCreate(AccountInterface $account) {

    // Only anonymous user is allowed to create new creative.
    if ($account->isAnonymous()) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

}
