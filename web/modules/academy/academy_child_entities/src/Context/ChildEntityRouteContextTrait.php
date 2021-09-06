<?php

namespace Drupal\academy_child_entities\Context;

/**
 * Trait to get the parent entity from the current route.
 *
 * Using this trait will add the getParentEntityFromRoute() method to the class.
 *
 * If the class is capable of injecting services from the container, it should
 * inject the 'current_route_match' service and
 * assign them to the currentRouteMatch and entityTypeManager properties.
 */
trait ChildEntityRouteContextTrait {

  /**
   * The current route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Gets the current route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The current route match object.
   */
  protected function getCurrentRouteMatch() {
    if (!$this->currentRouteMatch) {
      $this->currentRouteMatch = \Drupal::service('current_route_match');
    }
    return $this->currentRouteMatch;
  }

  /**
   * Retrieves the parent entity from the current route.
   *
   * This will try to load the parent entity from the route if present. If we
   * are on the group add form, it will return a new group entity with the group
   * type set.
   *
   * @param string $parent_entity_type
   *   The parent entity type machine name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The parent entity if one could be found, NULL otherwise.
   */
  public function getParentEntityFromRoute(string $parent_entity_type) {
    $route_match = $this->getCurrentRouteMatch();

    // See if the route has a group parameter and try to retrieve it.
    if ($parent_entity = $route_match->getParameter($parent_entity_type)) {
      return $parent_entity;
    }

    return NULL;
  }

}
