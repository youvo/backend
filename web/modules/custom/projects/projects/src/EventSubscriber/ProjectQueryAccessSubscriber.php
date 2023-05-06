<?php

namespace Drupal\projects\EventSubscriber;

use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines access conditions for project queries.
 *
 * @todo Needs to be worked out, when project entity access is refined.
 */
class ProjectQueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * Modifies the access conditions based on the current user.
   */
  public function onQueryAccess(QueryAccessEvent $event) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'entity.query_access.project' => 'onQueryAccess',
    ];
  }

}
