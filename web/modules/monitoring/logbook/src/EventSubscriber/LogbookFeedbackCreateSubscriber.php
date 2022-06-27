<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook feedback create event subscriber.
 */
class LogbookFeedbackCreateSubscriber extends LogbookFeedbackCompleteSubscriber {

  const EVENT_CLASS = 'Drupal\feedback\Event\FeedbackCreateEvent';
  const LOG_PATTERN = 'feedback_create';

}
