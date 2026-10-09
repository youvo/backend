<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\organizations\Event\OrganizationManageEvent;

/**
 * Logbook organization manage event subscriber.
 */
class LogbookOrganizationManageSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = OrganizationManageEvent::class;
  const LOG_PATTERN = 'organization_manage';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog($event)) {
      return;
    }
    /** @var \Drupal\organizations\Event\OrganizationManageEvent $event */
    $log->setOrganization($event->getOrganization());
    $log->setManager($event->getManager());
    $log->save();
  }

}
