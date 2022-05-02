<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides Project Complete Resource.
 *
 * @RestResource(
 *   id = "project:complete",
 *   label = @Translation("Project Complete Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/complete"
 *   }
 * )
 */
class ProjectCompleteResource extends ProjectTransitionResourceBase {

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

    if ($project->lifecycle()->complete()) {
      $project->save();
      $this->eventDispatcher->dispatch(
        new ProjectCompleteEvent($this->currentUser, $project)
      );
      return new ModifiedResourceResponse('Project completed.');
    }
    else {
      throw new ConflictHttpException('Project can not be completed.');
    }

  }

}
