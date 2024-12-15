<?php

namespace Drupal\feedback\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\feedback\FeedbackInterface;

/**
 * Defines a feedback complete event.
 */
class FeedbackCompleteEvent extends Event {

  /**
   * Constructs a FeedbackCompleteEvent object.
   */
  public function __construct(protected FeedbackInterface $feedback) {}

  /**
   * Gets the feedback.
   */
  public function getFeedback(): FeedbackInterface {
    return $this->feedback;
  }

}
