<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if unable to save project.
   */
  public function post(ProjectInterface $project, Request $request) {

    if ($project->lifecycle()->publish()) {
      $project->save();
      $this->eventDispatcher->dispatch(
        new ProjectPublishEvent($this->currentUser, $project, $request)
      );
      return new ModifiedResourceResponse('Project published.');
    }
    else {
      throw new ConflictHttpException('Project can not be published.');
    }

  }

}
