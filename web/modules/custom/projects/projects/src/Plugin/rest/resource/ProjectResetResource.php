<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project reset resource.
 *
 * @RestResource(
 *   id = "project:reset",
 *   label = @Translation("Project Reset Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/reset"
 *   }
 * )
 */
class ProjectResetResource extends ProjectTransitionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {
    // This resource may only be permitted for users with access control bypass.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    $bybass_permission = WorkflowPermissions::bypassTransition($workflow_id);
    return AccessResult::allowedIfHasPermission($account, $bybass_permission);
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    try {
      $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
    }
    // @codeCoverageIgnoreStart
    // This exception is not possible with the current configuration.
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be reset.');
    }
    // @codeCoverageIgnoreEnd
    catch (\Throwable) {
    }
    return new ModifiedResourceResponse('Project reset.');
  }

}
