<?php

namespace Drupal\projects\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides the project routes, with the admin forms for administrators only.
 *
 * Organizations, managers and supervisors manage projects through the frontend
 * and the manager pane. The entity forms save the action fields (status,
 * promote, lifecycle) without events, so only administrators may use them.
 * The canonical route keeps the entity view access.
 */
class ProjectHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * The permission required for the project admin forms.
   */
  public const string PERMISSION = 'access project admin forms';

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type): ?Route {
    return $this->restrict(parent::getAddFormRoute($entity_type));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type): ?Route {
    return $this->restrict(parent::getEditFormRoute($entity_type));
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type): ?Route {
    return $this->restrict(parent::getDeleteFormRoute($entity_type));
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type): ?Route {
    return $this->restrict(parent::getCollectionRoute($entity_type));
  }

  /**
   * Requires the project admin forms permission on a route.
   */
  protected function restrict(?Route $route): ?Route {
    return $route?->setRequirement('_permission', static::PERMISSION);
  }

}
