<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\feedback\Event\FeedbackCreateEvent;

/**
 * Logbook feedback create event subscriber.
 */
class LogbookFeedbackCreateSubscriber extends LogbookFeedbackCompleteSubscriber {

  const string EVENT_CLASS = FeedbackCreateEvent::class;
  const string LOG_PATTERN = 'feedback_create';

}
