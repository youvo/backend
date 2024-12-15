<?php

namespace Drupal\oauth_grant_remote\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('oauth2_token.authorize')) {
      $route->setDefaults([
        '_controller' => '\Drupal\oauth_grant_remote\Controller\Oauth2AuthorizeRemoteController::authorize',
      ]);
    }
  }

}
