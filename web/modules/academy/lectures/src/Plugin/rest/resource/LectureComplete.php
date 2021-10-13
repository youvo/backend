<?php

namespace Drupal\lectures\Plugin\rest\resource;

use Drupal\lectures\Entity\Lecture;
use Drupal\quizzes\Entity\Quiz;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "lecture:complete",
 *   label = @Translation("Lecture Complete Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/lectures/{lecture}/complete",
 *     "create" = "/api/lectures/{lecture}/complete"
 *   }
 * )
 */
class LectureComplete extends ResourceBase {

  /**
   * Responds GET requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The referenced lecture.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get(Lecture $lecture) {

    // Fetch questions and answers.
    $submission['type'] = $lecture->bundle();
    $submission['uuid'] = $lecture->uuid();

    // Compile response with structured data.
    $response = new ResourceResponse([
      'type' => 'lecture.resource.complete',
      'data' => $submission,
    ]);

    // Add cacheable dependency to refresh response when lecture is udpated.
    $response->addCacheableDependency($lecture);

    return $response;
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The referenced lecture.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function post(Lecture $lecture, Request $request) {

    // Decode content of the request.
    $request_content = \Drupal::service('serialization.json')->decode($request->getContent());

    return new ResourceResponse('Hello POST.', 200);
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $collection = new RouteCollection();

    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $create_path = $definition['uri_paths']['create'];
    $route_name = strtr($this->pluginId, ':', '.');

    $methods = $this->availableMethods();
    foreach ($methods as $method) {
      $path = $method === 'POST'
        ? $create_path
        : $canonical_path;
      $route = $this->getBaseRoute($path, $method);

      // Add custom access check.
      $route->setRequirement('_custom_access', '\Drupal\lectures\Controller\LectureAccessController::accessLecture');

      // Add route entity context parameters.
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'lecture' => [
          'type' => 'entity:lecture',
          'converter' => 'paramconverter.uuid',
        ],
      ]);

      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
