<?php

namespace Drupal\child_entities;

use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;

/**
 * Provides a trait for parent information.
 */
trait ChildEntityEnsureTrait {

  /**
   * Checks the implementation of the child entity.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   *   Thrown when the entity type does not implement ChildEntityInterface
   *   or if it does not have "parent" and "weight" entity keys.
   */
  public static function entityImplementsChildEntityInterface($entity_type): void {

    if (!$entity_type->entityClassImplements(ChildEntityInterface::class)) {
      throw new UnsupportedEntityTypeDefinitionException(
        'The entity type ' . $entity_type->id() . ' does not implement the ChildEntityInterface.');
    }
    if (!$entity_type->hasKey('parent') || !$entity_type->hasKey('weight')) {
      throw new UnsupportedEntityTypeDefinitionException(
        'The entity type ' . $entity_type->id() . ' does not have a "parent" or "weight" entity key.');
    }
  }

}
