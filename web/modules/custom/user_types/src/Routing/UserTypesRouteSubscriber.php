<?php

namespace Drupal\user_types\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\user_types\Controller\UserTypeController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters the routes of user accounts for the user types.
 */
class UserTypesRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $collection->get('entity.user.canonical')
      ?->setDefault('_title_callback', UserTypeController::class . '::userTitle');
  }

}
