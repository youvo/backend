<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides Project Reset Resource.
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
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    if (!$project->lifecycle()->reset()) {
      throw new ConflictHttpException('Project can not be reset.');
    }
    $project->setPromoted(FALSE);
    $project->save();
    $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
    return new ModifiedResourceResponse('Project reset.');
  }

}
