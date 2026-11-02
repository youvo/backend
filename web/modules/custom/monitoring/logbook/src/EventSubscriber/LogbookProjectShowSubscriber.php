<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectShowEvent;

/**
 * Logbook project show event subscriber.
 */
class LogbookProjectShowSubscriber extends LogbookProjectNotifySubscriber {

  const string EVENT_CLASS = ProjectShowEvent::class;
  const string LOG_PATTERN = 'project_show';

}
