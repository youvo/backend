<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\LectureProgressManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Abstract for Lecture Progress Resources.
 */
abstract class LectureProgressResource extends ResourceBase {

  /**
   * The progress manager service.
   *
   * @var \Drupal\progress\LectureProgressManager
   */
  protected $progressManager;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
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
   * @param \Drupal\progress\LectureProgressManager $progress_manager
   *   The progress manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, LectureProgressManager $progress_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->progressManager = $progress_manager;
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
      $container->get('progress.manager')
    );
  }

  /**
   * Responds GET requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The referenced lecture.
   *
   * @return \Drupal\rest\ResourceResponse|ModifiedResourceResponse
   *   Response.
   */
  public function get(Lecture $lecture) {

    try {
      // Get the respective lecture progress by lecture and current user.
      $progress = $this->progressManager->getLectureProgress($lecture);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new HttpException(417, 'The progress of the requested lecture has inconsistent persistent data.', $e);
    }

    // There is no progress for this lecture by this user.
    if (empty($progress)) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'resource' => strtr($this->pluginId, ':', '.'),
      'data' => [
        'type' => $progress->getEntityTypeId(),
        'enrolled' => $progress->getEnrollmentTime(),
        'accessed' => $progress->getAccessTime(),
        'completed' => $progress->getCompletedTime(),
        'lecture' => [
          'type' => $lecture->getEntityTypeId(),
          'uuid' => $lecture->uuid(),
        ],
      ],
    ]);

    // Add cacheable dependency to refresh response when lecture is udpated.
    $response->addCacheableDependency($progress);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = strtr($this->pluginId, ':', '.');

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', '\Drupal\progress\Controller\LectureProgressAccessController::accessLecture');
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
