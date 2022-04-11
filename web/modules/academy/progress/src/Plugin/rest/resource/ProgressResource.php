<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\academy\AcademicFormatInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\progress\ProgressManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Abstract for Lecture Progress Resources.
 */
abstract class ProgressResource extends ResourceBase {

  /**
   * The progress manager service.
   *
   * @var \Drupal\progress\ProgressManager
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
   * @param \Drupal\progress\ProgressManager $progress_manager
   *   The progress manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, ProgressManager $progress_manager) {
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
   * @param \Drupal\academy\AcademicFormatInterface $entity
   *   The referenced lecture or course.
   *
   * @return \Drupal\rest\ResourceResponse|ModifiedResourceResponse
   *   Response.
   */
  public function get(AcademicFormatInterface $entity) {

    try {
      // Get the respective progress by lecture or course and current user.
      $progress = $this->progressManager->loadProgress($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new UnprocessableEntityHttpException('The requested progress has inconsistent persistent data.', $e);
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
        'referenced_entity' => [
          'type' => $entity->getEntityTypeId(),
          'uuid' => $entity->uuid(),
        ],
      ],
    ]);

    // Add cacheable dependency to refresh response when lecture or course is
    // udpated.
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
    $entity_type = $this->getEntityTypeIdFromPluginId();

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', '\Drupal\progress\Controller\ProgressResourceAccessController::accessProgress');
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'entity' => [
          'type' => 'entity:' . $entity_type,
          'converter' => 'paramconverter.uuid',
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

  /**
   * Gets the entity type ID from the plugin ID.
   *
   * @return string
   *   The entity type ID.
   */
  private function getEntityTypeIdFromPluginId() {
    $plugin_id_substrings = explode(':', $this->pluginId);
    return $plugin_id_substrings[1];
  }

}
