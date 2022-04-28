<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectActionsAccess;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides base class for project action resources.
 */
class ProjectActionResourceBase extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

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
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountInterface $current_user,
    EventDispatcherInterface $event_dispatcher
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('projects'),
      $container->get('current_user'),
      $container->get('event_dispatcher')
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
          'type' => 'entity:node',
          'converter' => 'paramconverter.uuid',
        ],
      ]);

      // Check if access callback exists and set requirement.
      if (!empty($access_callback)) {
        $access_class = ProjectActionsAccess::class;
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
