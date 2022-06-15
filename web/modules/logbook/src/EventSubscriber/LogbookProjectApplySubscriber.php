<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;

/**
 * Logbook project apply event subscriber.
 */
class LogbookProjectApplySubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectApplyEvent';
  const LOG_PATTERN = 'project_apply';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectApplyEvent $event */
    $log->setProject($event->getProject());
    $log->setCreatives([$this->currentUser]);
    $log->setMessage($event->getMessage());
    $log->save();
  }

}
