<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides base class for project transition resources.
 */
class ProjectTransitionResourceBase extends ResourceBase {

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
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

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
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountInterface $current_user,
    EventDispatcherInterface $event_dispatcher,
    RouteProviderInterface $route_provider,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->eventDispatcher = $event_dispatcher;
    $this->routeProvider = $route_provider;
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
      $container->get('router.route_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $collection = new RouteCollection();

    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = strtr($this->getPluginId(), ':', '.');

    // The base route is not available during installation. Route definition
    // will be updated during first cache rebuild.
    $base_route_array = $this->routeProvider->getRoutesByNames([$route_name]);
    $base_route = !empty($base_route_array) ? reset($base_route_array) : NULL;

    $methods = $this->availableMethods();
    foreach ($methods as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);

      // Bequeath route definition from base route.
      if ($base_route instanceof Route) {
        if (!$this->baseRouteProper($base_route)) {
          throw new InvalidParameterException('Transition path has to provide transition default, _custom_access requirement and entity:node parameters.');
        }
        $route->setDefault('transition', $base_route->getDefault('transition'));
        $route->setRequirement('_custom_access', $base_route->getRequirement('_custom_access'));
        $route->addOptions(['parameters' => $base_route->getOption('parameters')]);
      }

      // Appended method is crucial for recognition in parameter converter.
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

  /**
   * Checks if base route was properly defined in routing.yml.
   *
   * @param \Symfony\Component\Routing\Route $base_route
   *   Base route of transition.
   */
  private function baseRouteProper(Route $base_route) {
    if (empty($base_route->getDefault('transition')) ||
      empty($base_route->getRequirement('_custom_access')) ||
      empty($base_route->getOption('parameters'))) {
      return FALSE;
    }
    return TRUE;
  }

}
