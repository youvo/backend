<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;

/**
 * Logbook organization manage event subscriber.
 */
class LogbookOrganizationManageSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = 'Drupal\organizations\Event\OrganizationManageEvent';
  const LOG_PATTERN = 'organization_manage';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\organizations\Event\OrganizationManageEvent $event */
    $log->setOrganization($event->getOrganization());
    $log->setManager($event->getManager());
    $log->save();
  }

}
