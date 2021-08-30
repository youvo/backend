<?php

namespace Drupal\youvo_projects\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "project_mediate",
 *   label = @Translation("Project Mediate Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{id}/mediate"
 *   }
 * )
 */
class ProjectMediateResource extends ResourceBase {

  /**
   * Responds GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get() {
    $response = ['message' => 'Mediate Project.'];
    return new ResourceResponse($response);
  }

}
