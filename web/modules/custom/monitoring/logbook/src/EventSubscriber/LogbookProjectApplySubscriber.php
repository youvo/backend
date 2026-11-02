<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectApplyEvent;

/**
 * Logbook project apply event subscriber.
 */
class LogbookProjectApplySubscriber extends LogbookSubscriberBase {

  const string EVENT_CLASS = ProjectApplyEvent::class;
  const string LOG_PATTERN = 'project_apply';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectApplyEvent $event */
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    $log->setCreatives([$event->getApplicant()]);
    $log->setMessage($event->getMessage());
    $log->save();
  }

}
