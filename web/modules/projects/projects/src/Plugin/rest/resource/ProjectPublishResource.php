<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides Project Publish Resource.
 *
 * @RestResource(
 *   id = "project:publish",
 *   label = @Translation("Project Publish Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/publish"
 *   }
 * )
 */
class ProjectPublishResource extends ProjectTransitionResourceBase {

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

    if ($project->lifecycle()->publish()) {
      $project->save();
      $this->eventDispatcher->dispatch(
        new ProjectPublishEvent($this->currentUser, $project)
      );
      return new ModifiedResourceResponse('Project published.');
    }
    else {
      throw new ConflictHttpException('Project can not be published.');
    }

  }

}
