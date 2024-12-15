<?php

namespace Drupal\feedback\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Symfony\Component\Routing\Route;

/**
 * Access controller for feedback create rest resources.
 */
class FeedbackCreateAccess {

  /**
   * Checks access for creating feedback.
   */
  public function access(AccountInterface $account, Route $route, ?ProjectInterface $project = NULL): AccessResultInterface {
    if (!$project instanceof ProjectInterface) {
      return new AccessResultNeutral();
    }
    $methods = $route->getMethods();
    $rest_resource = str_replace('.', ':', $route->getDefault('_rest_resource_config'));
    return AccessResult::allowedIf(
      $account->hasPermission('restful ' . strtolower(reset($methods)) . ' ' . $rest_resource) &&
      $project->lifecycle()->isCompleted() &&
      ($project->isParticipant($account) || $project->getOwner()->isManager($account) || $project->getOwnerId() == $account->id())
    );
  }

}
