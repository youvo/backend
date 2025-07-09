<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectNotifyEvent;

/**
 * Logbook project notify event subscriber.
 */
class LogbookProjectNotifySubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = ProjectNotifyEvent::class;
  const LOG_PATTERN = 'project_notify';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->save();
  }

}
