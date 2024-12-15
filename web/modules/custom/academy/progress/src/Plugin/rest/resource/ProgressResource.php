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
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an abstract for lecture progress resources.
 */
abstract class ProgressResource extends ResourceBase {

  /**
   * The progress manager.
   */
  protected ProgressManager $progressManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->progressManager = $container->get('progress.manager');
    return $instance;
  }

  /**
   * Responds to GET requests.
   */
  public function get(AcademicFormatInterface $entity): ResourceResponseInterface {

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
    if ($progress === NULL) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'resource' => str_replace(':', '.', $this->pluginId),
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
    // updated.
    $response->addCacheableDependency($progress);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->pluginId);
    $entity_type = $this->getEntityTypeIdFromPluginId();

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', '\Drupal\progress\Access\ProgressResourceAccess::accessProgress');
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
   */
  private function getEntityTypeIdFromPluginId(): string {
    $plugin_id_substrings = explode(':', $this->pluginId);
    return $plugin_id_substrings[1] ?? '';
  }

}
