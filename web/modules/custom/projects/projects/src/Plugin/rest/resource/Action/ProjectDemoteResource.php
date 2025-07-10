<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectDemoteEvent;
use Drupal\projects\Event\ProjectPromoteEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;

/**
 * Provides project demote resource.
 *
 * @RestResource(
 *   id = "project:demote",
 *   label = @Translation("Project Demote Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/demote"
 *   }
 * )
 */
class ProjectDemoteResource extends ProjectActionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer projects');
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    try {
      $this->eventDispatcher->dispatch(new ProjectDemoteEvent($project));
    }
    catch (\Throwable) {
    }
    return new ModifiedResourceResponse('Project demoted.');
  }

}
