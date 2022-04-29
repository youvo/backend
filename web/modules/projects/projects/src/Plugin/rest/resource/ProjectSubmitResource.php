<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides Project Submit Resource.
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

    if ($project->lifecycle()->submit()) {
      $project->save();
      $this->eventDispatcher->dispatch(
        new ProjectSubmitEvent($this->currentUser, $project, $request)
      );
      return new ModifiedResourceResponse('Project submitted.');
    }
    else {
      throw new ConflictHttpException('Project can not be submitted.');
    }

  }

}
