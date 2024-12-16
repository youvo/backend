<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Permissions;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;

/**
 * Access controller for project transitions.
 */
class ProjectTransitionAccess {

  /**
   * Checks access for project transition.
   */
  public function accessTransition(AccountInterface $account, ProjectInterface $project, string $transition): AccessResultInterface {

    // Bypass access control.
    if ($account->hasPermission('bypass project_lifecycle transition access')) {
      return AccessResult::allowed();
    }

    // Add access logic for different parties.
    $party_access = AccessResult::allowed();

    // Parties for transition submit.
    if (($transition === ProjectTransition::SUBMIT->value) && !$project->isAuthor($account)) {
      $party_access = AccessResult::forbidden();
    }

    // Parties for transition publish.
    if (($transition === ProjectTransition::PUBLISH->value) && !$project->getOwner()->isManager($account)) {
      $party_access = AccessResult::forbidden();
    }

    // Parties for transition mediate.
    if (
      ($transition === ProjectTransition::MEDIATE->value) &&
      !$project->isAuthor($account) &&
      !$project->getOwner()->isManager($account)
    ) {
      $party_access = AccessResult::forbidden();
    }

    // Parties for transition complete.
    if (
      ($transition === ProjectTransition::COMPLETE->value) &&
      !$project->isAuthor($account) &&
      !$project->isParticipant($account) &&
      !$project->getOwner()->isManager($account)
    ) {
      $party_access = AccessResult::forbidden();
    }

    return $party_access->andIf(
      AccessResult::allowedIf(
        $project->isPublished() &&
        Permissions::useTransition($account, 'project_lifecycle', $transition) &&
        $project->lifecycle()->canTransition(ProjectTransition::from($transition))
      )
    );
  }

}
