<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectPublishEvent;

/**
 * Logbook project publish event subscriber.
 */
class LogbookProjectPublishSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = ProjectPublishEvent::class;
  const LOG_PATTERN = 'project_publish';

}
