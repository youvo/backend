<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
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

    // The user may be permitted to bypass access control.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    $bybass_permission = WorkflowPermissions::bypassTransition($workflow_id);
    if ($account->hasPermission($bybass_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // The user may not have the permission to initiate this transition.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::MEDIATE->value);
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // The resource should define project-dependent access conditions.
    $project_condition = $project->isPublished() && $project->isAuthor($account);
    $access_project = AccessResult::allowedIf($project_condition)
      ->addCacheableDependency($project);

    $access_result = $access_result->andIf($access_project);
    if ($access_result instanceof AccessResultReasonInterface) {
      $access_result->setReason('The project conditions for this transition are not met.');
    }
    return $access_result;
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
