<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook organization disband event subscriber.
 */
class LogbookOrganizationDisbandSubscriber extends LogbookOrganizationManageSubscriber {

  const EVENT_CLASS = 'Drupal\organizations\Event\OrganizationDisbandEvent';
  const LOG_PATTERN = 'organization_disband';

}
