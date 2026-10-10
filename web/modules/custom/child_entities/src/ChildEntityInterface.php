<?php

namespace Drupal\child_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an interface for entities that are children of a parent entity.
 */
interface ChildEntityInterface extends EntityInterface {

  /**
   * Returns the parent entity type name.
   */
  public function getParentEntityTypeId(): string;

  /**
   * Returns the parent entity type.
   */
  public function getParentEntityType(): EntityTypeInterface;

  /**
   * Checks if the parent is also a child entity.
   */
  public function isParentAnotherChildEntity(): bool;

  /**
   * Returns the entity parent's entity.
   *
   * @throws \RuntimeException
   *   Thrown when the parent is not set or does not exist anymore.
   */
  public function getParentEntity(): EntityInterface;

  /**
   * Sets the entity parent's entity.
   */
  public function setParentEntity(EntityInterface $parent): static;

  /**
   * Returns the entity parent's ID.
   *
   * @return int|null
   *   The parent ID, or NULL in case the parent ID field has not been set on
   *   the entity.
   */
  public function getParentId(): ?int;

  /**
   * Sets the entity parent's ID.
   */
  public function setParentId(int $id): static;

  /**
   * Gets origin entity of descendant tree.
   *
   * The origin is the first ancestor that is not a child entity itself.
   *
   * @throws \RuntimeException
   *   Thrown when a parent in the chain does not exist.
   */
  public function getOriginEntity(): EntityInterface;

}
