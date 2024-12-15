<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\projects\Event\ProjectInviteEvent;

/**
 * Logbook project invite event subscriber.
 */
class LogbookProjectInviteSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_CLASS = ProjectInviteEvent::class;
  const LOG_PATTERN = 'project_invite';

}
