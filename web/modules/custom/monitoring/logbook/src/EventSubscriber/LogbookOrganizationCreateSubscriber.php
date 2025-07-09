<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\organizations\Event\OrganizationCreateEvent;

/**
 * Logbook organization create event subscriber.
 */
class LogbookOrganizationCreateSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = OrganizationCreateEvent::class;
  const LOG_PATTERN = 'organization_create';
  // Should run after project is created.
  const PRIORITY = -100;

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\organizations\Event\OrganizationCreateEvent $event */
    $log->setOrganization($event->getOrganization());
    $log->setProject($event->getProjectId());
    $log->save();
  }

}
