<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Restricts access-checked log queries, such as the logbook view.
 *
 * Views does not check the entity access per row, so the rules of the log
 * access handler that do not depend on the organization apply here as well.
 *
 * @see \Drupal\logbook\LogAccessControlHandler
 */
class LogQueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * Hides archived logs from accounts without the permission.
   */
  public function onQueryAccess(QueryAccessEvent $event): void {
    $conditions = $event->getConditions();
    $conditions->addCacheContexts(['user.permissions']);
    if (!$event->getAccount()->hasPermission('view archived log')) {
      $conditions->addCondition('status', 1);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['entity.query_access.log' => 'onQueryAccess'];
  }

}
