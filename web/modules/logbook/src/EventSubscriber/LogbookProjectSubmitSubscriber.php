<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook project submit event subscriber.
 */
class LogbookProjectSubmitSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectSubmitEvent';
  const LOG_PATTERN = 'project_submit';

}
