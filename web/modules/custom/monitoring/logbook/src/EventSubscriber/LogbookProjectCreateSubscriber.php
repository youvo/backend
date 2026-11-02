<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectCreateEvent;

/**
 * Logbook project create event subscriber.
 */
class LogbookProjectCreateSubscriber extends LogbookProjectNotifySubscriber {

  const string EVENT_CLASS = ProjectCreateEvent::class;
  const string LOG_PATTERN = 'project_create';

}
