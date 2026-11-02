<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectMediateEvent;

/**
 * Logbook project mediate event subscriber.
 */
class LogbookProjectMediateSubscriber extends LogbookProjectCompleteSubscriber {

  const string EVENT_CLASS = ProjectMediateEvent::class;
  const string LOG_PATTERN = 'project_mediate';

}
