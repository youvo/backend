<?php

namespace Drupal\projects;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a project comment entity type.
 */
interface ProjectCommentInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, ChildEntityInterface {

  /**
   * Gets the project comment value.
   *
   * @return string
   *   The comment.
   */
  public function getComment(): string;

  /**
   * Gets the project comment creation timestamp.
   *
   * @return int
   *   Creation timestamp of the project comment.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the project comment creation timestamp.
   *
   * @param int $timestamp
   *   The project comment creation timestamp.
   *
   * @return $this
   *   The called project comment entity.
   */
  public function setCreatedTime(int $timestamp): static;

}
