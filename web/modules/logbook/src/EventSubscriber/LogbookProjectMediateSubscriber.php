<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook project mediate event subscriber.
 */
class LogbookProjectMediateSubscriber extends LogbookProjectCompleteSubscriber {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectMediateEvent';
  const LOG_PATTERN = 'project_mediate';

}
