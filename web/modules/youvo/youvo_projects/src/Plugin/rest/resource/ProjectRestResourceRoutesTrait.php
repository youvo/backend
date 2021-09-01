<?php

namespace Drupal\youvo_projects\Plugin\rest\resource;

use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines routes method for project rest resources.
 *
 * Note that the pluginId of the respective service has to match the routeId of
 * the matching base route which defines the administration form.
 */
trait ProjectRestResourceRoutesTrait {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $collection = new RouteCollection();

    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = strtr($this->pluginId, ':', '.');

    // The base route is not available during installation. Route definition
    // will be updated during first cache rebuild.
    $base_route = reset(\Drupal::service('router.route_provider')->getRoutesByNames([$route_name])) ?? NULL;

    $methods = $this->availableMethods();
    foreach ($methods as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      if ($base_route instanceof Route) {
        if (!$this->baseRouteProper($base_route)) {
          throw new InvalidParameterException('Transition path has to provide transition default, _custom_access requirement and entity:node parameters.');
        }
        $route->setDefault('transition', $base_route->getDefault('transition'));
        $route->setRequirement('_custom_access', $base_route->getRequirement('_custom_access'));
        $route->addOptions(['parameters' => $base_route->getOption('parameters')]);
      }

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
