<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectSubmitEvent;

/**
 * Logbook project submit event subscriber.
 */
class LogbookProjectSubmitSubscriber extends LogbookProjectNotifySubscriber {

  const string EVENT_CLASS = ProjectSubmitEvent::class;
  const string LOG_PATTERN = 'project_submit';

}
