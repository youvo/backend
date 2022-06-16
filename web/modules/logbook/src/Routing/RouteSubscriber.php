<?php

namespace Drupal\logbook\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribe to the Route to change JSON:API routes for log collection.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs a RouteSubscriber object.
   */
  public function __construct(protected ConfigFactoryInterface $config) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('jsonapi.log.collection')) {
      $prefix = $this->config
        ->get('jsonapi_extras.settings')
        ->get('path_prefix');
      $route->setPath($prefix . '/logs');
    }
  }

}
