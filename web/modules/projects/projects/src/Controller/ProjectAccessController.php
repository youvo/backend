<?php

namespace Drupal\projects\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;

/**
 * Access controller for transition forms.
 */
class ProjectAccessController extends ControllerBase {

  /**
   * Checks access for project transition.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\projects\Entity\Project|null $project
   *   The node id.
   * @param string $transition
   *   The requested transition. Defaults in route definition.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectTransition(AccountInterface $account, ProjectInterface $project = NULL, string $transition = '') {
    if ($project instanceof Project && !empty($transition)) {
      return AccessResult::allowedIf(
        $account->hasPermission('use project_lifecycle transition project_' . $transition) &&
        $project->canTransitionByLabel($transition)
      );
    }
    return AccessResult::forbidden();
  }

  /**
   * Checks access for project apply.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\projects\Entity\Project|null $project
   *   The node id.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectApply(AccountInterface $account, ProjectInterface $project = NULL) {
    if ($project instanceof Project) {
      return AccessResult::allowedIf(in_array('creative', $account->getRoles()));
    }
    return AccessResult::forbidden();
  }

}
