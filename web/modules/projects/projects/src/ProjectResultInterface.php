<?php

namespace Drupal\projects;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a project result entity type.
 */
interface ProjectResultInterface extends ContentEntityInterface, EntityChangedInterface, ChildEntityInterface {

  /**
   * Gets the project result creation timestamp.
   *
   * @return int
   *   Creation timestamp of the project result.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the project result creation timestamp.
   *
   * @param int $timestamp
   *   The project result creation timestamp.
   *
   * @return \Drupal\projects\ProjectResultInterface
   *   The called project result entity.
   */
  public function setCreatedTime(int $timestamp): ProjectResultInterface;

}
