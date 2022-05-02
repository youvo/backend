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
    return AccessResult::allowedIf(
      $project->isPublished() &&
      Permissions::useTransition($account, 'project_lifecycle', $transition) &&
      $project->lifecycle()->canTransition($transition)
    );
  }

}
