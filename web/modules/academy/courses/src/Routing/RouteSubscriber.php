<?php

namespace Drupal\courses\Routing;

use Drupal\child_entities\Controller\ChildEntityController;
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
    if ($route = $collection->get('entity.course.edit_form')) {
      $route->setDefault('_title_callback', ChildEntityController::class . '::editTitle');
    }
  }

}
