<?php

namespace Drupal\youvo_projects\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Youvo Projects route subscriber.
 */
class YouvoProjectsRouteSubscriber extends RouteSubscriberBase {

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
