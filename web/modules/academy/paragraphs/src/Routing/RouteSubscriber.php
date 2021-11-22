<?php

namespace Drupal\paragraphs\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribe to the Route to change page titles.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.paragraph.collection')) {
      $route->setRequirement('_permission', 'manage courses');
    }
  }

}
