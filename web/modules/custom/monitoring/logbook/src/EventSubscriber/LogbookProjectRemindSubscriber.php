<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectRemindEvent;

/**
 * Logbook project remind event subscriber.
 */
class LogbookProjectRemindSubscriber extends LogbookSubscriberBase {

  const string EVENT_CLASS = ProjectRemindEvent::class;
  const string LOG_PATTERN = 'project_remind';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectRemindEvent $event */
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->setMisc([
      'subtype' => $event->getSubtype(),
      'correspondence' => $event->getCorrespondence(),
    ]);
    $log->save();
  }

}
