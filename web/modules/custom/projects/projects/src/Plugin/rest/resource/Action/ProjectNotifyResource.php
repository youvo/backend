<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
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
class ProjectNotifyResource extends ProjectResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // Archived projects don't change, also not for administrators.
    if ($project->isArchived()) {
      return AccessResult::forbidden('The project is archived.')
        ->addCacheableDependency($project);
    }

    // The user requires the permission to do this action.
    $access_result = AccessResult::allowedIfHasPermission($account, 'restful post project:notify');

    // Only drafts are notified, also by administrators.
    $access_project = AccessResult::allowedIf($project->lifecycle()->isDraft())
      ->addCacheableDependency($project);
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this action are not met.');
    }
    $access_result = $access_result->andIf($access_project);

    // Administrators skip the manager check.
    if ($account->hasPermission('administer projects')) {
      return $access_result;
    }

    // Only the manager of the organization notifies it.
    $organization = $project->getOwner();
    $access_manager = AccessResult::allowedIf($organization->isManager($account))
      ->addCacheableDependency($project)
      ->addCacheableDependency($organization)
      ->cachePerUser();
    if ($access_manager instanceof AccessResultReasonInterface) {
      $access_manager->setReason('The project conditions for this action are not met.');
    }

    return $access_result->andIf($access_manager);
  }

  /**
   * Responds to POST requests.
   *
   * Prospect organizations are invited, the others are notified.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {

    $invite = $project->getOwner()->hasRoleProspect();
    $event = $invite ? new ProjectInviteEvent($project) : new ProjectNotifyEvent($project);

    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project notify failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse($invite ? 'Project invited.' : 'Project notified.');
  }

}
