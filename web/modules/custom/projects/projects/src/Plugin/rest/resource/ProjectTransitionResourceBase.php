<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rest\Plugin\ResourceBase;
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
   */
  protected AccountInterface $currentUser;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The route provider.
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->logger = $container->get('logger.channel.projects');
    $instance->routeProvider = $container->get('router.route_provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {
    $collection = new RouteCollection();

    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->getPluginId());

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
   * Checks if the base route was properly defined in routing.yml.
   */
  private function baseRouteProper(Route $base_route): bool {
    return !(empty($base_route->getDefault('transition')) ||
      empty($base_route->getRequirement('_custom_access')) ||
      empty($base_route->getOption('parameters')));
  }

}
