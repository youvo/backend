<?php

namespace Drupal\user_types\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\user_types\Controller\UserTypeController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters the routes of user accounts for the user types.
 *
 * The profile page is for staff only. The frontend reads users through
 * JSON:API, which checks the entity access but not this route.
 */
class UserTypesRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('entity.user.canonical');
    $route?->setDefault('_title_callback', UserTypeController::class . '::userTitle');
    $route?->setRequirement('_permission', 'access site');
  }

}
