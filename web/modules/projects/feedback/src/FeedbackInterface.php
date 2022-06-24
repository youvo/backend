<?php

namespace Drupal\feedback;

use Drupal\Core\Entity\ContentEntityInterface;
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
  public function setCreatedTime(int $timestamp);

}
