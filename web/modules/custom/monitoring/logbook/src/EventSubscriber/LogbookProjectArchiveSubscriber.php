<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\Event\ProjectArchiveEvent;

/**
 * Logbook project archive event subscriber.
 */
class LogbookProjectArchiveSubscriber extends LogbookSubscriberBase {

  const string EVENT_CLASS = ProjectArchiveEvent::class;
  const string LOG_PATTERN = 'project_archive';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {

    // Archive the logs of the project. The archive log is created afterwards
    // and stays active.
    /** @var \Drupal\projects\Event\ProjectArchiveEvent $event */
    $project = $event->getProject();
    $this->setProjectLogsArchived($project);

    if (!$log = $this->createLog($event)) {
      return;
    }
    $log->setProject($project);
    $log->setOrganization($project->getOwner());
    if ($manager = $project->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->setMisc(['reason' => $event->getReason()->value]);
    // Keeps the mail even if sending fails or never happens: the subject in
    // the first line, then the body.
    if ($body = $event->getBody()) {
      $log->setMessage(trim($event->getSubject() . "\n\n" . $body));
    }
    $log->save();
  }

}
