<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Access\ProjectActionAccess;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides base class for project action resources.
 */
abstract class ProjectActionResourceBase extends ResourceBase {

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->logger = $container->get('logger.channel.projects');
    return $instance;
  }

  /**
   * Adds access callback to REST route collection.
   *
   * @param string $access_callback
   *   A string with the access callback.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route collection.
   */
  public function routesWithAccessCallback(string $access_callback): RouteCollection {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->pluginId);

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {

      // Use UUID parameter converter.
      $route = $this->getBaseRoute($canonical_path, $method);
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'project' => [
          'type' => 'entity:project',
          'converter' => 'paramconverter.uuid',
        ],
      ]);

      // Check if access callback exists and set requirement.
      if (!empty($access_callback)) {
        $access_class = ProjectActionAccess::class;
        if (method_exists($access_class, $access_callback)) {
          $route->setRequirement('_custom_access', $access_class . '::' . $access_callback);
        }
        else {
          throw new AccessException(sprintf('Unable to resolve access callback for %s.', $route_name));
        }
      }

      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
