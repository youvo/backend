<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\projects\Event\ProjectShowEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project show resource.
 *
 * @RestResource(
 *   id = "project:show",
 *   label = @Translation("Project Show Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/show"
 *   }
 * )
 */
class ProjectShowResource extends ProjectResourceBase {

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
    $access_result = AccessResult::allowedIfHasPermission($account, 'administer projects');

    // Only open, ongoing or completed projects are shown.
    $lifecycle = $project->lifecycle();
    $access_project = AccessResult::allowedIf($lifecycle->isOpen() || $lifecycle->isOngoing() || $lifecycle->isCompleted())
      ->addCacheableDependency($project);
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
      $this->eventDispatcher->dispatch(new ProjectShowEvent($project));
    }
    catch (\LogicException) {
      throw new ConflictHttpException('Project can not be shown.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project show failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project shown.');
  }

}
