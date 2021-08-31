<?php

namespace Drupal\youvo_projects\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\youvo_projects\ProjectInterface;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "youvo_projects:mediate",
 *   label = @Translation("Project Mediate Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/mediate"
 *   }
 * )
 */
class ProjectMediateResource extends ResourceBase {

  use ProjectRestResourceRoutesTrait;

  /**
   * Responds GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get(ProjectInterface $project) {
    $response = ['title' => $project->getTitle()];
    return new ResourceResponse($response);
  }

}
