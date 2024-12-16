<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;

/**
 * Provides Project Notify Resource.
 *
 * @RestResource(
 *   id = "project:notify",
 *   label = @Translation("Project Notify Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/notify"
 *   }
 * )
 */
class ProjectNotifyResource extends ProjectActionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    $access_result = AccessResult::allowed();

    // Supervisors may bypass the access check.
    if (in_array('supervisor', $account->getRoles(), TRUE)) {
      return $access_result->addCacheableDependency($project)->cachePerUser();
    }

    // The project may not be open to apply.
    if (!$project->isPublished() || !$project->lifecycle()->isDraft()) {
      $access_result = AccessResult::forbidden('The organization can not be notified for this project.');
    }

    // The project notification may only be triggered by its manager.
    if (!$project->getOwner()->isManager($account)) {
      $access_result = AccessResult::forbidden('The user does not manage the project.');
    }

    return $access_result->addCacheableDependency($project)->cachePerUser();
  }

  /**
   * Responds to POST requests.
   */
  public function post(Project $project): ResourceResponseInterface {
    if ($project->getOwner()->hasRoleProspect()) {
      $this->eventDispatcher->dispatch(new ProjectInviteEvent($project));
    }
    else {
      $this->eventDispatcher->dispatch(new ProjectNotifyEvent($project));
    }
    return new ModifiedResourceResponse();
  }

}
