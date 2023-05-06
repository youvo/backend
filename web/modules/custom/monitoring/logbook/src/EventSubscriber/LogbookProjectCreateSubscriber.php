<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook project publish event subscriber.
 */
class LogbookProjectCreateSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectCreateEvent';
  const LOG_PATTERN = 'project_create';

}
