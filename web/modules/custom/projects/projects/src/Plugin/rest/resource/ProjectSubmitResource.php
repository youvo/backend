<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    if (!$project->lifecycle()->submit()) {
      throw new ConflictHttpException('Project can not be submitted.');
    }
    $project->save();
    $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
    return new ModifiedResourceResponse('Project submitted.');
  }

}
