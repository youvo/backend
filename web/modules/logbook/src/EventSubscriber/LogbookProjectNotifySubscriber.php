<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;

/**
 * Logbook project notify event subscriber.
 */
class LogbookProjectNotifySubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectNotifyEvent';
  const LOG_PATTERN = 'project_notify';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    $log->setProject($event->getProject());
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $event->getProject()->getOwner();
    $log->setOrganization($organization);
    $log->setManager($this->currentUser);
    $log->save();
  }

}
