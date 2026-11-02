<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;

/**
 * Provides project notify resource.
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

    // The user requires the permission to do this action.
    $permission = 'restful post project:notify';
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // Only drafts can be notified, also by administrators.
    $access_state = AccessResult::allowedIf($project->lifecycle()->isDraft())
      ->addCacheableDependency($project);
    if ($access_state instanceof AccessResultReasonInterface) {
      $access_state->setReason('The project conditions for this action are not met.');
    }

    // Administrators skip the manager check.
    if ($account->hasPermission('administer projects')) {
      return $access_result->andIf($access_state);
    }

    $organization = $project->getOwner();
    $access_manager = AccessResult::allowedIf($project->isPublished() && $organization->isManager($account))
      ->addCacheableDependency($organization)
      ->addCacheableDependency($project)
      ->cachePerUser();
    if ($access_manager instanceof AccessResultReasonInterface) {
      $access_manager->setReason('The project conditions for this action are not met.');
    }

    return $access_result->andIf($access_state)->andIf($access_manager);
  }

  /**
   * Responds to POST requests.
   */
  public function post(Project $project): ResourceResponseInterface {

    if ($project->getOwner()->hasRoleProspect()) {
      $this->eventDispatcher->dispatch(new ProjectInviteEvent($project));
      return new ModifiedResourceResponse('The organization was invited.');
    }

    $this->eventDispatcher->dispatch(new ProjectNotifyEvent($project));
    return new ModifiedResourceResponse('The organization was notified.');
  }

}
