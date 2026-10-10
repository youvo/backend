<?php

namespace Drupal\child_entities\Form;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Base form for child entities.
 *
 * Takes the parent of a new child entity from the current route.
 */
class ChildEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {

    $entity = parent::getEntityFromRouteMatch($route_match, $entity_type_id);

    if ($entity instanceof ChildEntityInterface && $entity->isNew() && $entity->getParentId() === NULL) {
      $parent = $route_match->getParameter($entity->getParentEntityTypeId());
      if ($parent) {
        $entity->setParentEntity($parent);
      }
    }

    return $entity;
  }

}
