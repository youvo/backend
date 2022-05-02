<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Permissions;

/**
 * Access controller for project transitions.
 */
class ProjectTransitionAccess extends ControllerBase {

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
