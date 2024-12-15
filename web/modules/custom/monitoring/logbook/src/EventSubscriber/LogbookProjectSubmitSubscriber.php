<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectSubmitEvent;

/**
 * Logbook project submit event subscriber.
 */
class LogbookProjectSubmitSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = ProjectSubmitEvent::class;
  const LOG_PATTERN = 'project_submit';

}
