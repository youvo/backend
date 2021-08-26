<?php

namespace Drupal\youvo_lifecycle\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for transition forms.
 */
class AccessTransitionController {

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectMediate(AccountInterface $account) {
    return AccessResult::allowedIf($account->hasPermission('use project_lifecycle transition project_mediate'));
  }

}
