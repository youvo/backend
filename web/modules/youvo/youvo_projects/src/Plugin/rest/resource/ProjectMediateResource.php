<?php

namespace Drupal\youvo_projects\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\youvo_projects\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;

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
   * @param \Drupal\youvo_projects\ProjectInterface $project
   *   The referenced project.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get(ProjectInterface $project) {

    // Fetch applicants in desired structure.
    $applicants = [];
    foreach ($project->getApplicantsAsArray(TRUE) as $uuid => $applicant) {
      $applicants[] = [
        'type' => 'user',
        'id' => $uuid,
        'name' => $applicant,
      ];
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'type' => 'project.resource.mediate',
      'data' => [
        'type' => $project->getType(),
        'id' => $project->uuid(),
        'attributes' => [
          'title' => $project->getTitle(),
          'applicants' => $applicants,
        ],
      ],
      'post_required' => [
        'selected_participants' => 'Array of participants keyed by uuid.',
      ],
    ]);

    // Add cacheable dependency to refresh response when project is udpated.
    $response->addCacheableDependency($project);

    return $response;
  }

  /**
   * Responds PATCH requests.
   *
   * @param \Drupal\youvo_projects\ProjectInterface $project
   *   The referenced project.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function patch(ProjectInterface $project, Request $request) {

    return new ResourceResponse(['message' => $project->getTitle()]);
  }

}
