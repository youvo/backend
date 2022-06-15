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
   * Writes log during event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectApplyEvent $event */
    $log->setProject($event->getProject());
    // Log creatives on complete, because participants may change manually
    // while the project is ongoing.
    $log->setCreatives($event->getProject()->getParticipants());
    $log->setMessage($event->getMessage());
    $log->save();
  }

}
