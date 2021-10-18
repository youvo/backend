<?php

namespace Drupal\postman_interface\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides Postman Variables Resource.
 *
 * @RestResource(
 *   id = "postman:variables",
 *   label = @Translation("Postman Variables Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/postman"
 *   }
 * )
 */
class PostmanVariablesResource extends ResourceBase {

  /**
   * Responds GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get() {

    // Get some creative.
    // $database = \Drupal::database();
    // $query = $database->select('users', 'u')->fields('u', ['uuid']);
    // $query->join('user__roles', 'r', 'u.uid = r.entity_id');
    // $query->condition('u.uid', 1, '!=')
    // ->condition('r.roles_target_id', 'creative');
    // $creative_uuids = $query->execute()->fetchCol();
    // $creative_uuid = reset($creative_uuids);
    $creative_ids = \Drupal::entityQuery('user')
      ->condition('uid', 1, '!=')
      ->condition('roles', 'creative')
      ->execute();
    try {
      $creative = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load(reset($creative_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $creative = NULL;
    }

    // Get some lecture.
    $lecture_ids = \Drupal::entityQuery('lecture')
      ->execute();
    try {
      $lecture = \Drupal::entityTypeManager()
        ->getStorage('lecture')
        ->load(reset($lecture_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $lecture = NULL;
    }

    // Get some course.
    $course_ids = \Drupal::entityQuery('course')
      ->execute();
    try {
      $course = \Drupal::entityTypeManager()
        ->getStorage('course')
        ->load(reset($course_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $course = NULL;
    }

    // Get a project that can mediate.
    $project_ids = \Drupal::entityQuery('node')
      ->condition('type', 'project')
      ->condition('status', 1)
      ->condition('field_lifecycle', 'open')
      ->condition('field_applicants.%delta', 1, '>=')
      ->execute();
    try {
      $project_can_mediate = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load(reset($project_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_can_mediate = NULL;
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'type' => 'postman.variables.resource',
      'data' => [
        'creative' => $creative?->uuid(),
        'course' => $course?->uuid(),
        'lecture' => $lecture?->uuid(),
        'project_can_mediate' => $project_can_mediate?->uuid(),
      ],
    ]);

    // Prevent caching.
    $response->addCacheableDependency([
      '#cache' => [
        'max-age' => 0,
      ],
    ]);

    return $response;
  }

}
