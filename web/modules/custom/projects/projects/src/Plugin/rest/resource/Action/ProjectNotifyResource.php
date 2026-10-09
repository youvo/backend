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

    // The user may be permitted to bypass access control.
    // @todo Add specific permission.
    if (in_array('supervisor', $account->getRoles(), TRUE)) {
      return AccessResult::allowed()->addCacheContexts(['user.roles:supervisor'])->cachePerUser();
    }

    // The user requires the permission to do this action.
    $permission = 'restful post project:notify';
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // The resource should define project-dependent access conditions.
    $organization = $project->getOwner();
    $project_condition = $project->isPublished() && $project->lifecycle()->isDraft() && $organization->isManager($account);
    $access_project = AccessResult::allowedIf($project_condition)
      ->addCacheableDependency($organization)
      ->addCacheableDependency($project);
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this action are not met.');
    }

    return $access_result->andIf($access_project);
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
