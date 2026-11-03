<?php

namespace Drupal\organizations\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\organizations\Controller\OrganizationController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters the routes of user accounts for organizations.
 */
class OrganizationsRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // The page of an organization is titled with its name, not with the email
    // that is its account name.
    $collection->get('entity.user.canonical')
      ?->setDefault('_title_callback', OrganizationController::class . '::userTitle');
  }

}
