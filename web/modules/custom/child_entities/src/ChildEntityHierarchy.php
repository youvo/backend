<?php

namespace Drupal\child_entities;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Resolves the parent hierarchy of child entity types.
 *
 * Child entity types declare their parent entity type through the "parent"
 * entity key. This service is the single place that walks that chain.
 */
class ChildEntityHierarchy {

  /**
   * Constructs a ChildEntityHierarchy object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Checks whether the entity type is a child entity type.
   */
  public function isChildEntityType(EntityTypeInterface $entity_type): bool {
    return $entity_type->entityClassImplements(ChildEntityInterface::class);
  }

  /**
   * Returns the parent entity type of a child entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getParentEntityType(EntityTypeInterface $entity_type): EntityTypeInterface {
    return $this->entityTypeManager->getDefinition($entity_type->getKey('parent'));
  }

  /**
   * Returns all ancestor entity types, nearest first.
   *
   * The last element is the origin entity type, i.e. the first ancestor that is
   * not a child entity type itself.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   The ancestor entity types, keyed by entity type ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAncestorEntityTypes(EntityTypeInterface $entity_type): array {
    $ancestors = [];
    $current = $entity_type;
    while ($this->isChildEntityType($current)) {
      $current = $this->getParentEntityType($current);
      $ancestors[$current->id()] = $current;
    }
    return $ancestors;
  }

  /**
   * Returns the origin entity type of the descendant tree.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getOriginEntityType(EntityTypeInterface $entity_type): EntityTypeInterface {
    $ancestors = $this->getAncestorEntityTypes($entity_type);
    return $ancestors ? end($ancestors) : $entity_type;
  }

  /**
   * Returns the entity type IDs of all ancestors, nearest first.
   *
   * These are also the names of the route parameters that identify the
   * ancestors on child entity routes.
   *
   * @return string[]
   *   The ancestor entity type IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAncestorEntityTypeIds(EntityTypeInterface $entity_type): array {
    return array_keys($this->getAncestorEntityTypes($entity_type));
  }

  /**
   * Returns all child entity types mapped to their parent entity type ID.
   *
   * @return string[]
   *   The parent entity type IDs, keyed by child entity type ID.
   */
  public function getChildEntityTypes(): array {
    $child_entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($this->isChildEntityType($definition)) {
        $child_entity_types[$definition->id()] = $definition->getKey('parent');
      }
    }
    return $child_entity_types;
  }

}
