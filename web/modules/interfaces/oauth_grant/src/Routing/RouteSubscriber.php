<?php

namespace Drupal\oauth_grant\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('simple_oauth.userinfo')) {
      $route->setDefaults([
        '_controller' => '\Drupal\oauth_grant\Controller\UserInfoOverwriteController::handle',
      ]);
    }
  }

}
