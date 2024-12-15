<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\Routing\RouteCollection;

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
   */
  public function post(Project $project): ResourceResponseInterface {
    if ($project->getOwner()->hasRoleProspect()) {
      $this->eventDispatcher->dispatch(new ProjectInviteEvent($project));
    }
    else {
      $this->eventDispatcher->dispatch(new ProjectNotifyEvent($project));
    }
    return new ModifiedResourceResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {
    return $this->routesWithAccessCallback('accessNotify');
  }

}
