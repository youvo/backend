<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectPromoteEvent;

/**
 * Logbook project promote event subscriber.
 */
class LogbookProjectPromoteSubscriber extends LogbookProjectNotifySubscriber {

  const string EVENT_CLASS = ProjectPromoteEvent::class;
  const string LOG_PATTERN = 'project_promote';

}
