<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\organizations\Event\OrganizationDisbandEvent;

/**
 * Logbook organization disband event subscriber.
 */
class LogbookOrganizationDisbandSubscriber extends LogbookOrganizationManageSubscriber {

  const EVENT_CLASS = OrganizationDisbandEvent::class;
  const LOG_PATTERN = 'organization_disband';

}
