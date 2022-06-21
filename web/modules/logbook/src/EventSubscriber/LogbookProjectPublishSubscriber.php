<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook project publish event subscriber.
 */
class LogbookProjectPublishSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectPublishEvent';
  const LOG_PATTERN = 'project_publish';

}
