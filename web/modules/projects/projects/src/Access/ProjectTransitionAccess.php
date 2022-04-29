<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Permissions;

/**
 * Access controller for transition forms.
 */
class ProjectTransitionAccess extends ControllerBase {

  /**
   * Checks access for project transition.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\projects\ProjectInterface|null $project
   *   The project.
   * @param string $transition
   *   The requested transition. Defaults in route definition.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectTransition(AccountInterface $account, ProjectInterface $project = NULL, string $transition = '') {
    if ($project instanceof ProjectInterface && !empty($transition)) {
      return AccessResult::allowedIf(
        Permissions::useTransition($account, 'project_lifecycle', $transition) &&
        $project->lifecycle()->canTransition($transition)
      );
    }
    return AccessResult::forbidden();
  }

}
