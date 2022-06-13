<?php

namespace Drupal\logbook\EventSubscriber;

/**
 * Logbook project invite event subscriber.
 */
class LogbookProjectInviteSubscriber extends LogbookProjectNotifySubscriber {

  const EVENT_TYPE = 'project_invite';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['Drupal\projects\Event\ProjectInviteEvent' => 'log'];
  }

}
