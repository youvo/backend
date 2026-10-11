<?php

namespace Drupal\mailer\Manager;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;

/**
 * Access checks for sending manager mails.
 */
class ManagerMailAccess {

  /**
   * Constructs the access service.
   */
  public function __construct(protected ManagerMailRegistry $registry) {}

  /**
   * Whether the account manages the project (assigned manager or admin).
   */
  public function canManage(AccountInterface $account, ProjectInterface $project): bool {
    if ($account->hasPermission('administer projects')) {
      return TRUE;
    }
    $owner = $project->getOwner();
    return $owner instanceof Organization && $owner->isManager($account);
  }

  /**
   * Checks access to the mail pane (empty subtype) or a specific mail.
   */
  public function access(AccountInterface $account, ProjectInterface $project, string $subtype = ''): AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'send project mails')
      ->andIf(AccessResult::allowedIf($this->canManage($account, $project)))
      ->addCacheableDependency($project)
      ->cachePerUser()
      ->cachePerPermissions();
    if ($subtype !== '') {
      $result = $result->andIf(AccessResult::allowedIf($this->registry->find($project, $subtype) !== NULL));
    }
    return $result;
  }

}
