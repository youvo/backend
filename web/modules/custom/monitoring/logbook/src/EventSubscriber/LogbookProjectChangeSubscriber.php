<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectChangeEvent;

/**
 * Logbook project change event subscriber.
 */
class LogbookProjectChangeSubscriber extends LogbookSubscriberBase {

  const string EVENT_CLASS = ProjectChangeEvent::class;
  const string LOG_PATTERN = 'project_change';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectChangeEvent $event */
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->setMisc([
      'fields' => $event->getFields(),
      'previous_revision_id' => $event->getPreviousRevisionId(),
      'revision_id' => $event->getRevisionId(),
    ]);
    $log->save();
  }

}
