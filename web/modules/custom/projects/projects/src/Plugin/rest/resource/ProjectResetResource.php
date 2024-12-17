<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\ProjectInterface;
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

  protected const TRANSITION = 'reset';

  /**
   * {@inheritdoc}
   */
  protected static function projectAccessCondition(AccountInterface $account, ProjectInterface $project): bool {
    return FALSE;
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    try {
      $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
    }
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be reset.');
    }
    catch (\Throwable) {
    }
    return new ModifiedResourceResponse('Project reset.');
  }

}
