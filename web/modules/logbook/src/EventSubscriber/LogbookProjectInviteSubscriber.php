<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook project invite event subscriber.
 */
class LogbookProjectInviteSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectInviteEvent';
  const LOG_PATTERN = 'project_invite';

}
