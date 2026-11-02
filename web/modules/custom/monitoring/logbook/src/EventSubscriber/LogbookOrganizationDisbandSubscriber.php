<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\organizations\Event\OrganizationDisbandEvent;

/**
 * Logbook organization disband event subscriber.
 */
class LogbookOrganizationDisbandSubscriber extends LogbookOrganizationManageSubscriber {

  const string EVENT_CLASS = OrganizationDisbandEvent::class;
  const string LOG_PATTERN = 'organization_disband';

}
