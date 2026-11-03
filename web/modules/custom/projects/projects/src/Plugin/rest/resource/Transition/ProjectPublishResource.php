<?php

namespace Drupal\projects\Plugin\rest\resource\Transition;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project publish resource.
 */
#[RestResource(
  id: 'project:publish',
  label: new TranslatableMarkup('Project Publish Resource'),
  uri_paths: [
    'canonical' => '/api/projects/{project}/publish',
  ],
)]
class ProjectPublishResource extends ProjectResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // Archived projects don't transition, also not for administrators.
    if ($project->isArchived()) {
      return AccessResult::forbidden('The project is archived.')
        ->addCacheableDependency($project);
    }

    // The user may be permitted to bypass access control.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    if ($account->hasPermission(WorkflowPermissions::bypassTransition($workflow_id))) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerPermissions();
    }

    // The user requires the permission to initiate this transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::Publish->value);
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // Only the manager of the organization publishes its projects.
    $organization = $project->getOwner();
    $access_project = AccessResult::allowedIf($organization->isManager($account))
      ->addCacheableDependency($project)
      ->addCacheableDependency($organization)
      ->cachePerUser();
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this transition are not met.');
    }

    return $access_result->andIf($access_project);
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {

    try {
      $this->eventDispatcher->dispatch(new ProjectPublishEvent($project));
    }
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be published.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project publish failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project published.');
  }

}
