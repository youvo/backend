<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\rest\ModifiedResourceResponse;

/**
 * Provides Project Notify Resource.
 *
 * @RestResource(
 *   id = "project:notify",
 *   label = @Translation("Project Notify Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/notify"
 *   }
 * )
 */
class ProjectNotifyResource extends ProjectActionResourceBase {

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function post(Project $project) {
    $event = new ProjectNotifyEvent($this->currentUser, $project);
    $this->eventDispatcher->dispatch($event);
    return new ModifiedResourceResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    return $this->routesWithAccessCallback('accessProjectNotify');
  }

}
