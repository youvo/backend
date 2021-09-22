<?php

namespace Drupal\child_entities;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for access to an entity's published state.
 */
interface ChildEntityInterface extends EntityInterface {

  /**
   * Returns the parent entity type name.
   *
   * @return string
   *   The parent Entity Type machine name.
   */
  public function getParentEntityTypeId();

  /**
   * Returns the parent entity type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   The parent entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getParentEntityType();

  /**
   * Check if the Parent is also a Child Entity.
   *
   * @return bool
   *   True if the Parent is also a Child Entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function isParentAnotherChildEntity();

  /**
   * Returns the entity parent's entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The parent entity.
   */
  public function getParentEntity();

  /**
   * Sets the entity parent's entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $parent
   *   The parent entity.
   *
   * @return $this
   *   The updated child entity.
   */
  public function setParentEntity(EntityInterface $parent);

  /**
   * Returns the entity parent's ID.
   *
   * @return int|null
   *   The parent ID, or NULL in case the parent ID field has not been set on
   *   the entity.
   */
  public function getParentId();

  /**
   * Sets the entity parent's ID.
   *
   * @param int $id
   *   The parent id.
   *
   * @return $this
   *   The updated child entity.
   */
  public function setParentId(int $id);

  /**
   * Gets origin entity of descendant tree.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The origin entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getOriginEntity();

}
