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

  /**
   * Sets the files by file IDs.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   An array of file IDs.
   *
   * @return \Drupal\projects\ProjectResultInterface
   *   The called project result entity.
   */
  public function setFiles(array $files): ProjectResultInterface;

  /**
   * Sets the hyperlinks field.
   *
   * @param array $hyperlinks
   *   An array of hyperlinks.
   *
   * @return \Drupal\projects\ProjectResultInterface
   *   The called project result entity.
   */
  public function setHyperlinks(array $hyperlinks): ProjectResultInterface;

}
