<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectHideEvent;

/**
 * Logbook project hide event subscriber.
 */
class LogbookProjectHideSubscriber extends LogbookProjectNotifySubscriber {

  const string EVENT_CLASS = ProjectHideEvent::class;
  const string LOG_PATTERN = 'project_hide';

}
