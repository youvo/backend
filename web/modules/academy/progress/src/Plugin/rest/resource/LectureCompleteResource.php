<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\LectureProgress;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Progress Lecture Complete Resource.
 *
 * @RestResource(
 *   id = "progress:lecture:complete",
 *   label = @Translation("Progress Lecture Complete Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/lectures/{lecture}/complete",
 *     "create" = "/api/lectures/{lecture}/complete"
 *   }
 * )
 */
class LectureCompleteResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $serializationJson;

  /**
   * Constructs a QuestionSubmissionResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Serialization\Json $serialization_json
   *   The serialization by Json service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, Json $serialization_json) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->serializationJson = $serialization_json;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('serialization.json')
    );
  }

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
      'type' => 'progress.lecture.complete.resource',
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
    $request_content = $this->serializationJson
      ->decode($request->getContent());

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
      $route->setRequirement('_custom_access', '\Drupal\progress\Controller\LectureProgressAccessController::accessLecture');

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
