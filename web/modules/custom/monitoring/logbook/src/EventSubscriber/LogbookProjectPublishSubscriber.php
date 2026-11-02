<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectPublishEvent;

/**
 * Logbook project publish event subscriber.
 */
class LogbookProjectPublishSubscriber extends LogbookProjectNotifySubscriber {

  const string EVENT_CLASS = ProjectPublishEvent::class;
  const string LOG_PATTERN = 'project_publish';

}
