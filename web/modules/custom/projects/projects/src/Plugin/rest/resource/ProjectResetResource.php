<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\ProjectInterface;
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

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if unable to save project.
   */
  public function post(ProjectInterface $project) {

    if ($project->lifecycle()->reset()) {
      $project->setPromoted(FALSE);
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
      return new ModifiedResourceResponse('Project reset.');
    }
    else {
      throw new ConflictHttpException('Project can not be reset.');
    }

  }

}
