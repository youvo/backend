<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines routes method for project action REST resources.
 */
trait ProjectActionRestResourceRoutesTrait {
  
  /**
   * {@inheritdoc}
   */
  abstract public function getPluginId();

  /**
   * {@inheritdoc}
   */
  abstract public function availableMethods();

  /**
   * {@inheritdoc}
   */
  abstract public function getPluginDefinition();

  /**
   * {@inheritdoc}
   */
  abstract protected function getBaseRoute($canonical_path, $method);

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
        $access_class = '\Drupal\projects\ProjectActionsAccess';
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
