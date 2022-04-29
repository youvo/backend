<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
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
   * @param \Drupal\projects\ProjectInterface|null $project
   *   The project.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectApply(AccountInterface $account, ProjectInterface $project = NULL) {
    if ($project instanceof ProjectInterface) {
      return AccessResult::allowedIf(
        Profile::isCreative($account) &&
        $project->lifecycle()->isOpen()
      );
    }
    return AccessResult::forbidden();
  }

  /**
   * Checks access for project notify action.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\projects\ProjectInterface|null $project
   *   The project.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProjectNotify(AccountInterface $account, ProjectInterface $project = NULL) {
    if ($project instanceof ProjectInterface) {
      return AccessResult::allowedIf(
        $project->lifecycle()->isDraft() &&
        $project->isAuthorOrManager($account)
      );
    }
    return AccessResult::forbidden();
  }

}
