<?php

namespace Drupal\feedback\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides the feedback routes, with the canonical route for staff only.
 *
 * The canonical route keeps the entity view access. Authors fill in their
 * feedback through the frontend, which uses JSON:API and the REST resource.
 */
class FeedbackHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route {
    return parent::getCanonicalRoute($entity_type)?->setRequirement('_permission', 'access site');
  }

}
