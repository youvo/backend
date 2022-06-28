<?php

namespace Drupal\feedback;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\projects\ProjectInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

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
   *
   * @param int $timestamp
   *   The feedback creation timestamp.
   *
   * @return \Drupal\feedback\FeedbackInterface
   *   The called feedback entity.
   */
  public function setCreatedTime(int $timestamp): FeedbackInterface;

  /**
   * Sets the completed timestamp for the feedback entity.
   *
   * @return \Drupal\feedback\FeedbackInterface
   *   The called feedback entity.
   */
  public function complete(): FeedbackInterface;

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
