<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;

/**
 * Logbook project publish event subscriber.
 */
class LogbookProjectPublishSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectPublishEvent';
  const LOG_PATTERN = 'project_publish';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectApplyEvent $event */
    $log->setProject($event->getProject());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->save();
  }

}
