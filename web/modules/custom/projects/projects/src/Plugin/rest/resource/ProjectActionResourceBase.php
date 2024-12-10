<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Access\ProjectActionAccess;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides base class for project action resources.
 */
abstract class ProjectActionResourceBase extends ResourceBase {

  /**
   * Constructs a ProjectActionResourceBase object.
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
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializationJson
   *   Serialization with Json.
   * @param \Drupal\user\UserStorageInterface $userStorage
   *   The user storage.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected AccountInterface $currentUser,
    protected EventDispatcherInterface $eventDispatcher,
    protected SerializationInterface $serializationJson,
    protected UserStorageInterface $userStorage,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('projects'),
      $container->get('current_user'),
      $container->get('event_dispatcher'),
      $container->get('serialization.json'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * Adds access callback to REST route collection.
   *
   * @param string $access_callback
   *   A string with the access callback.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route.
   */
  public function routesWithAccessCallback(string $access_callback) {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = strtr($this->pluginId, ':', '.');

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
