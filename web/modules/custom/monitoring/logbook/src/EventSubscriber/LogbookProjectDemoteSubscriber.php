<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectDemoteEvent;

/**
 * Logbook project demote event subscriber.
 */
class LogbookProjectDemoteSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = ProjectDemoteEvent::class;
  const LOG_PATTERN = 'project_demote';

}
