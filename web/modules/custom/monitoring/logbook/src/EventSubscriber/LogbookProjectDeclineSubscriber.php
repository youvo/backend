<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectDeclineEvent;

/**
 * Logbook project decline event subscriber.
 */
class LogbookProjectDeclineSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = ProjectDeclineEvent::class;
  const LOG_PATTERN = 'project_decline';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectDeclineEvent $event */
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->setMisc(['reason' => $event->getReason()->value]);
    $log->save();
  }

}
