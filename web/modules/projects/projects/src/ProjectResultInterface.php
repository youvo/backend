<?php

namespace Drupal\projects;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a project result entity type.
 */
interface ProjectResultInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the project result creation timestamp.
   *
   * @return int
   *   Creation timestamp of the project result.
   */
  public function getCreatedTime();

  /**
   * Sets the project result creation timestamp.
   *
   * @param int|string $timestamp
   *   The project result creation timestamp.
   *
   * @return \Drupal\project_results\ProjectResultInterface
   *   The called project result entity.
   */
  public function setCreatedTime(int|string $timestamp);

}
