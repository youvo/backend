<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\projects\Event\ProjectDemoteEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
class ProjectDemoteResource extends ProjectResourceBase {

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

    // Declined projects keep their flag.
    $access_project = AccessResult::allowedIf(!$project->lifecycle()->isDeclined())
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
      $this->eventDispatcher->dispatch(new ProjectDemoteEvent($project));
    }
    catch (\LogicException) {
      throw new ConflictHttpException('Project can not be demoted.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project demote failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project demoted.');
  }

}
