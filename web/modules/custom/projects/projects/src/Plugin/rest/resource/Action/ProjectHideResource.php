<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\projects\Event\ProjectHideEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project hide resource.
 *
 * @RestResource(
 *   id = "project:hide",
 *   label = @Translation("Project Hide Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/hide"
 *   }
 * )
 */
class ProjectHideResource extends ProjectActionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // The user requires the permission to do this action.
    $access_result = AccessResult::allowedIfHasPermission($account, 'administer projects');

    // Only active open, ongoing or completed projects are hidden.
    $access_project = AccessResult::allowedIf(
      $project->isActive() &&
      ($project->lifecycle()->isOpen() || $project->lifecycle()->isOngoing() || $project->lifecycle()->isCompleted())
    )->addCacheableDependency($project);
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this action are not met.');
    }

    return $access_result->andIf($access_project);
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    try {
      $this->eventDispatcher->dispatch(new ProjectHideEvent($project));
    }
    catch (\LogicException) {
      throw new ConflictHttpException('Project can not be hidden.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project hide failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }
    return new ModifiedResourceResponse('Project hidden.');
  }

}
