<?php

namespace Drupal\feedback;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\projects\ProjectInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a feedback entity type.
 */
interface FeedbackInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the feedback creation timestamp.
   *
   * @return int
   *   Creation timestamp of the feedback.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the feedback creation timestamp.
   */
  public function setCreatedTime(int $timestamp): static;

  /**
   * Sets the completed timestamp for the feedback entity.
   */
  public function complete(): static;

  /**
   * Checks whether the feedback is completed.
   *
   * @return bool
   *   Is completed?
   */
  public function isCompleted(): bool;

  /**
   * Gets the project.
   *
   * @return \Drupal\projects\ProjectInterface
   *   The project.
   */
  public function getProject(): ProjectInterface;

}
