<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectMediateEvent;

/**
 * Logbook project mediate event subscriber.
 */
class LogbookProjectMediateSubscriber extends LogbookProjectCompleteSubscriber {

  const EVENT_CLASS = ProjectMediateEvent::class;
  const LOG_PATTERN = 'project_mediate';

}
