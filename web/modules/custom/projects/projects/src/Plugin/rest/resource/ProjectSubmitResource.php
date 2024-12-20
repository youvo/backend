<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project submit resource.
 *
 * @RestResource(
 *   id = "project:submit",
 *   label = @Translation("Project Submit Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/submit"
 *   }
 * )
 */
class ProjectSubmitResource extends ProjectTransitionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    $access_result = AccessResult::allowed();

    // The user may be permitted to bypass access control.
    $bybass_permission = WorkflowPermissions::bypassTransition($workflow_id);
    if ($account->hasPermission($bybass_permission)) {
      return $access_result->cachePerPermissions();
    }

    // The resource should define project-dependent access conditions.
    if (!$project->isAuthor($account)) {
      $access_result = AccessResult::forbidden('The project conditions for this transition are not met.');
    }

    // The project should be able to perform the given transition.
    if (!$project->isPublished()) {
      $access_result = AccessResult::forbidden('The project is not ready for this transition.');
    }

    // The user may not have the permission to initiate this transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::SUBMIT->value);
    if (!$account->hasPermission($permission)) {
      $access_result = AccessResult::forbidden('The user is not allowed to initiate this transition.');
    }

    return $access_result->addCacheableDependency($project)->cachePerUser();
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    try {
      $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
    }
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be submitted.');
    }
    catch (\Throwable) {
    }
    return new ModifiedResourceResponse('Project submitted.');
  }

}
