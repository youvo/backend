<?php

namespace Drupal\projects\Plugin\rest\resource;

use Symfony\Component\Routing\RouteCollection;

/**
 * Provides method to define project resource routes.
 */
trait ProjectResourceRoutesTrait {

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {

    $collection = new RouteCollection();

    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->getPluginId());

    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', static::class . '::access');
      $route->addOptions([
        'parameters' => [
          'project' => [
            'type' => 'entity:project',
            'converter' => 'paramconverter.uuid',
          ],
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
