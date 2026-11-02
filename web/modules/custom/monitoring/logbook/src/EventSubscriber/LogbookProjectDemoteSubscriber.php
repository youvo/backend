<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectDemoteEvent;

/**
 * Logbook project demote event subscriber.
 */
class LogbookProjectDemoteSubscriber extends LogbookProjectNotifySubscriber {

  const string EVENT_CLASS = ProjectDemoteEvent::class;
  const string LOG_PATTERN = 'project_demote';

}
