<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectCompleteEvent;

/**
 * Logbook project complete event subscriber.
 */
class LogbookProjectCompleteSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = ProjectCompleteEvent::class;
  const LOG_PATTERN = 'project_complete';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectCompleteEvent $event */
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    $log->setCreatives($event->getProject()->getParticipants());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->save();
  }

}
