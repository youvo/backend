<?php

namespace Drupal\youvo_projects\Plugin\rest\resource;

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
    $base_route = reset(\Drupal::service('router.route_provider')->getRoutesByNames([$route_name])) ?? NULL;

    $methods = $this->availableMethods();
    foreach ($methods as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);

      if ($base_route instanceof Route && !empty($base_route->getDefault('transition'))) {
        $route->setDefault('transition', $base_route->getDefault('transition'));
      }

      if ($base_route instanceof Route && !empty($base_route->getRequirement('_custom_access'))) {
        $route->setRequirement('_custom_access', $base_route->getRequirement('_custom_access'));
      }

      if ($base_route instanceof Route && !empty($base_route->getOption('parameters'))) {
        $route->addOptions(['parameters' => $base_route->getOption('parameters')]);
      }

      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
