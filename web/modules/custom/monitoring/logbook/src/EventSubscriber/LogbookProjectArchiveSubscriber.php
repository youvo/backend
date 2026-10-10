<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectArchiveEvent;

/**
 * Logbook project archive event subscriber.
 */
class LogbookProjectArchiveSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = ProjectArchiveEvent::class;
  const LOG_PATTERN = 'project_archive';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectArchiveEvent $event */
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->setMessage($event->getMessage());
    if ($reason = $event->getReason()) {
      $log->setMisc(['reason' => $reason->value]);
    }
    $log->save();
  }

}
