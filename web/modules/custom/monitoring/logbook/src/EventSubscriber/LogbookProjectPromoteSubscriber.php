<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectPromoteEvent;

/**
 * Logbook project promote event subscriber.
 */
class LogbookProjectPromoteSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = ProjectPromoteEvent::class;
  const LOG_PATTERN = 'project_promote';

}
