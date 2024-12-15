<?php

namespace Drupal\courses;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a course entity type.
 */
interface CourseInterface extends ContentEntityInterface {

  /**
   * Gets the course title.
   */
  public function getTitle(): string;

  /**
   * Sets the course title.
   */
  public function setTitle(string $title): static;

  /**
   * Gets the course machine name.
   */
  public function getMachineName(): string;

  /**
   * Sets the course machine name.
   */
  public function setMachineName(string $machine_name): static;

  /**
   * Gets the course creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the course creation timestamp.
   */
  public function setCreatedTime(int $timestamp): static;

}
