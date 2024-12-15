<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\feedback\Event\FeedbackCreateEvent;

/**
 * Logbook feedback create event subscriber.
 */
class LogbookFeedbackCreateSubscriber extends LogbookFeedbackCompleteSubscriber {

  const EVENT_CLASS = FeedbackCreateEvent::class;
  const LOG_PATTERN = 'feedback_create';

}
