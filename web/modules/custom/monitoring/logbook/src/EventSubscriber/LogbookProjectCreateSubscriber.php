<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectCreateEvent;

/**
 * Logbook project publish event subscriber.
 */
class LogbookProjectCreateSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = ProjectCreateEvent::class;
  const LOG_PATTERN = 'project_create';

}
