<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectResetEvent;

/**
 * Logbook project reset event subscriber.
 */
class LogbookProjectResetSubscriber extends LogbookSubscriberBase {

  const string EVENT_CLASS = ProjectResetEvent::class;
  const string LOG_PATTERN = 'project_reset';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {

    // Archive the earlier logs of the project. The reset log is created
    // afterwards and stays active.
    /** @var \Drupal\projects\Event\ProjectResetEvent $event */
    $this->setProjectLogsArchived($event->getProject());

    // Log reset of project.
    if (!$log = $this->createLog($event)) {
      return;
    }
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->save();
  }

}
