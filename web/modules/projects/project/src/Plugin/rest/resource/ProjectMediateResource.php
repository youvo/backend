<?php

namespace Drupal\project\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\project\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "project:mediate",
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
   * @param \Drupal\project\ProjectInterface $project
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
      'resource' => strtr($this->pluginId, ':', '.'),
      'data' => [
        'type' => $project->getType(),
        'id' => $project->uuid(),
        'attributes' => [
          'title' => $project->getTitle(),
        ],
        'relationships' => [
          'applicants' => [
            'data' => $applicants,
          ],
        ],
      ],
      'patch_required' => [
        'selected_participants' => 'Array of uuid\'s of participants.',
      ],
    ]);

    // Add cacheable dependency to refresh response when project is udpated.
    $response->addCacheableDependency($project);

    return $response;
  }

  /**
   * Responds PATCH requests.
   *
   * @param \Drupal\project\ProjectInterface $project
   *   The referenced project.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   */
  public function patch(ProjectInterface $project, Request $request) {

    // Decode content of the request.
    $request_content = \Drupal::service('serialization.json')->decode($request->getContent());

    // The selected_participants are required to process the request.
    if (!array_key_exists('selected_participants', $request_content)) {
      throw new BadRequestHttpException('Request body does not specify selected_participants.');
    }

    // Set preliminary selected_participants variable.
    $selected_participants = array_unique($request_content['selected_participants']);

    // Force at least one selected participant.
    if (empty($selected_participants)) {
      throw new BadRequestHttpException('The selected_participants array in the request body is empty.');
    }

    // The selected_participants is expected to be delivered as a simple array.
    if (count(array_filter(array_keys($selected_participants), 'is_string')) > 0) {
      throw new BadRequestHttpException('The selected_participants array in the request body is malformed.');
    }

    // The entries of the selected participants array are expected to be UUIDs.
    if (count(array_filter($selected_participants,
        ['Drupal\Component\Uuid\Uuid', 'isValid'])) != count($selected_participants)) {
      throw new BadRequestHttpException('The entries of the selected_participants array are not valid UUIDs.');
    }

    // Get applicants for current project and check if selected_participants are
    // applicable.
    $applicants = array_unique(array_keys($project->getApplicantsAsArray(TRUE)));
    if (count(array_intersect($selected_participants, $applicants)) != count($selected_participants)) {
      throw new UnprocessableEntityHttpException('Some selected participants did not apply for this project.');
    }

    // Now we are finally sure to mediate the project. We get the UIDs by query.
    $selected_participants_ids = \Drupal::entityQuery('user')
      ->condition('uuid', $selected_participants, 'IN')
      ->execute();
    if (!empty($selected_participants_ids) && $project->transitionMediate()) {
      $project->setParticipants($selected_participants_ids, TRUE);
      return new ResourceResponse('Project was mediated successfully.');
    }

    throw new UnprocessableEntityHttpException('Could not mediate project.');
  }

}
