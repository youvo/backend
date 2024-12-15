<?php

namespace Drupal\progress;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a lecture progress entity.
 */
interface ProgressInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the lecture enrollment timestamp.
   */
  public function getEnrollmentTime(): int;

  /**
   * Gets the lecture access timestamp.
   */
  public function getAccessTime(): int;

  /**
   * Sets the lecture access timestamp.
   */
  public function setAccessTime(int $timestamp): static;

  /**
   * Gets the lecture completed timestamp.
   */
  public function getCompletedTime(): int;

  /**
   * Sets the lecture completed timestamp.
   */
  public function setCompletedTime(int $timestamp): static;

}
