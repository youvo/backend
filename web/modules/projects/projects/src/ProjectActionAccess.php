<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Entity\Project;
use Drupal\user_types\Utility\Profile;

/**
 * Access controller for transition forms.
 */
class ProjectActionAccess extends ControllerBase {

  /**
   * Checks access for project apply.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\projects\Entity\Project|null $project
   *   The project.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectApply(AccountInterface $account, ProjectInterface $project = NULL) {
    if ($project instanceof Project) {
      return AccessResult::allowedIf(
        Profile::isCreative($account) &&
        $project->workflowManager()->isOpen()
      );
    }
    return AccessResult::forbidden();
  }

  /**
   * Checks access for project notify action.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\projects\Entity\Project|null $project
   *   The project.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectNotify(AccountInterface $account, ProjectInterface $project = NULL) {
    if ($project instanceof Project) {
      return AccessResult::allowedIf(
        $project->workflowManager()->isDraft() &&
        $project->isAuthorOrManager($account)
      );
    }
    return AccessResult::forbidden();
  }

}
