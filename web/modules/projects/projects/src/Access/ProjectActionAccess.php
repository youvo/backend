<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\user_types\Utility\Profile;

/**
 * Provides access checks for project actions.
 */
class ProjectActionAccess {

  /**
   * Checks access for project apply.
   */
  public function accessApply(AccountInterface $account, ProjectInterface $project): AccessResult {
    return AccessResult::allowedIf(
      $project->isPublished() &&
      Profile::isCreative($account) &&
      $project->lifecycle()->isOpen()
    );
  }

  /**
   * Checks access for notify action.
   */
  public function accessNotify(AccountInterface $account, ProjectInterface $project): AccessResult {
    return AccessResult::allowedIf(
      $project->isPublished() &&
      $project->lifecycle()->isDraft() &&
      $project->isAuthorOrManager($account)
    );
  }

}
