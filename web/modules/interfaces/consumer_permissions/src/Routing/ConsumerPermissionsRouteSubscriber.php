<?php

namespace Drupal\consumer_permissions\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber for consumer permissions.
 */
class ConsumerPermissionsRouteSubscriber extends RouteSubscriberBase {

  /**
   * Alters OAuth authorization route.
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('oauth2_token.authorize')) {
      $route->setRequirements([
        '_custom_access' => '\Drupal\consumer_permissions\Controller\ConsumerPermissionsAccess::accessClient',
      ]);
    }
  }

}
