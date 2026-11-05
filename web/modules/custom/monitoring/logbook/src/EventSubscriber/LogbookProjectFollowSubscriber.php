<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Entity\Creative;
use Drupal\projects\Event\ProjectFollowEvent;

/**
 * Logbook project follow event subscriber.
 */
class LogbookProjectFollowSubscriber extends LogbookSubscriberBase {

  const string EVENT_CLASS = ProjectFollowEvent::class;
  const string LOG_PATTERN = 'project_follow';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectFollowEvent $event */
    $recipient = $event->getRecipient();
    $log->setOwner($recipient);
    $log->setProject($event->getProject());
    $log->setOrganization($event->getProject()->getOwner());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    if ($recipient instanceof Creative) {
      $log->setCreatives([$recipient]);
    }
    $log->setMisc([
      'subtype' => $event->getSubtype(),
      'correspondence' => $event->getCorrespondence(),
      'target' => $event->getTarget(),
    ]);
    $log->save();
  }

}
