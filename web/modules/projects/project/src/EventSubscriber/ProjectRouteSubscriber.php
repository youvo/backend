<?php

namespace Drupal\project\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Projects route subscriber.
 */
class ProjectRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // @todo Change logic when other content types land.
    if ($route = $collection->get('system.admin_content')) {
      $route->setDefault('_title', 'Projects');
    }
  }

}
