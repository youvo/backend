<?php

namespace Drupal\projects\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Projects route subscriber.
 */
class ProjectRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // @todo Change logic when other content types land.
    if ($route = $collection->get('system.admin_content')) {
      $route->setDefault('_title', 'Projects');
    }
  }

}
