<?php

namespace Drupal\feedback\Access;

use Drupal\Core\Access\AccessResult;
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
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Symfony\Component\Routing\Route $route
   *   The requested route.
   * @param \Drupal\projects\ProjectInterface|null $project
   *   The project.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function access(AccountInterface $account, Route $route, ProjectInterface $project = NULL) {
    if (!$project instanceof ProjectInterface) {
      return new AccessResultNeutral();
    }
    $methods = $route->getMethods();
    $rest_resource = strtr($route->getDefault('_rest_resource_config'), '.', ':');
    return AccessResult::allowedIf(
      $account->hasPermission('restful ' . strtolower(reset($methods)) . ' ' . $rest_resource) &&
      $project->lifecycle()->isCompleted() &&
      ($project->isParticipant($account) || $project->getOwner()->isManager($account) || $project->getOwnerId() == $account->id())
    );
  }

}
