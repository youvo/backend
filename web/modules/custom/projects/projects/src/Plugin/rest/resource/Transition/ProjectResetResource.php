<?php

namespace Drupal\projects\Plugin\rest\resource\Transition;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project reset resource.
 */
#[RestResource(
  id: 'project:reset',
  label: new TranslatableMarkup('Project Reset Resource'),
  uri_paths: [
    'canonical' => '/api/projects/{project}/reset',
  ],
)]
class ProjectResetResource extends ProjectResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // Archived projects don't transition, also not for administrators.
    if ($project->isArchived()) {
      return AccessResult::forbidden('The project is archived.')
        ->addCacheableDependency($project);
    }

    // Only users who bypass the transition access reset projects.
    $permission = WorkflowPermissions::bypassTransition(ProjectLifecycle::WORKFLOW_ID);
    return AccessResult::allowedIfHasPermission($account, $permission)
      ->addCacheableDependency($project);
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
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project reset failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project reset.');
  }

}
