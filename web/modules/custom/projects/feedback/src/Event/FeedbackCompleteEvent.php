<?php

namespace Drupal\feedback\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\feedback\FeedbackInterface;
use Drupal\youvo\Event\TimestampedEventInterface;

/**
 * Defines a feedback complete event.
 */
class FeedbackCompleteEvent extends Event implements TimestampedEventInterface {

  /**
   * Constructs a FeedbackCompleteEvent object.
   *
   * @param \Drupal\feedback\FeedbackInterface $feedback
   *   The feedback.
   * @param int|null $timestamp
   *   The time the feedback was completed, NULL for now.
   */
  public function __construct(
    protected FeedbackInterface $feedback,
    protected ?int $timestamp = NULL,
  ) {}

  /**
   * Gets the feedback.
   */
  public function getFeedback(): FeedbackInterface {
    return $this->feedback;
  }

  /**
   * Gets the time the feedback was completed, NULL for now.
   */
  public function getTimestamp(): ?int {
    return $this->timestamp;
  }

}
