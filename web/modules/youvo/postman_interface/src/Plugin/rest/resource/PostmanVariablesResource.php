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
    $creative_ids = \Drupal::entityQuery('user')
      ->condition('uid', 1, '!=')
      ->condition('roles', 'creative')
      ->execute();
    try {
      $test_creative = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load(reset($creative_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $test_creative = [];
    }

    // Compile response with structured data.
    return new ResourceResponse([
      'type' => 'postman.variables.resource',
      'data' => [
        'creative_uuid' => !empty($test_creative) ? $test_creative->uuid() : NULL,
      ],
    ]);
  }

}
