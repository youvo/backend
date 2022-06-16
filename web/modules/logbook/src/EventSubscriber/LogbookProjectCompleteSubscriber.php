<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;

/**
 * Logbook project complete event subscriber.
 */
class LogbookProjectCompleteSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectCompleteEvent';
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
    $log->setCreatives($event->getProject()->getParticipants());
    $log->setManager($event->getProject()->getOwner()->getManager());
    $log->save();
  }

}
