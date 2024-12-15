<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    if (!$project->lifecycle()->publish()) {
      throw new ConflictHttpException('Project can not be published.');
    }
    $project->save();
    $this->eventDispatcher->dispatch(new ProjectPublishEvent($project));
    return new ModifiedResourceResponse('Project published.');
  }

}
