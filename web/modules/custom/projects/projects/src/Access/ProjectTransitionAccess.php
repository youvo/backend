<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Permissions;
use Drupal\projects\ProjectInterface;

/**
 * Access controller for project transitions.
 */
class ProjectTransitionAccess {

  /**
   * Checks access for project transition.
   */
  public function accessTransition(AccountInterface $account, ProjectInterface $project, string $transition): AccessResult {

    // Bypass access control.
    if ($account->hasPermission('bypass project_lifecycle transition access')) {
      return AccessResult::allowed();
    }

    // Add access logic for different parties.
    $party_access = AccessResult::allowed();

    // Parties for transition submit.
    if ($transition == 'submit') {
      if (!$project->isAuthor($account)) {
        $party_access = AccessResult::forbidden();
      }
    }

    // Parties for transition publish.
    if ($transition == 'publish') {
      if (!$project->getOwner()->isManager($account)) {
        $party_access = AccessResult::forbidden();
      }
    }

    // Parties for transition mediate.
    if ($transition == 'mediate') {
      if (
        !$project->isAuthor($account) &&
        !$project->getOwner()->isManager($account)
      ) {
        $party_access = AccessResult::forbidden();
      }
    }

    // Parties for transition complete.
    if ($transition == 'complete') {
      if (
        !$project->isAuthor($account) &&
        !$project->getOwner()->isManager($account) &&
        !$project->isParticipant($account)
      ) {
        $party_access = AccessResult::forbidden();
      }
    }

    return $party_access->andIf(
      AccessResult::allowedIf(
        $project->isPublished() &&
        Permissions::useTransition($account, 'project_lifecycle', $transition) &&
        $project->lifecycle()->canTransition($transition)
      )
    );
  }

}
