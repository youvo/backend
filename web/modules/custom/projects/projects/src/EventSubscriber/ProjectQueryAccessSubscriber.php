<?php

namespace Drupal\projects\EventSubscriber;

use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Drupal\user_types\Utility\Profile;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Restricts access-checked project queries, such as JSON:API collections.
 *
 * Views and collections do not check the entity access per item, so the rules
 * of the project access handler that the project's own fields express apply
 * here as well. The rest stays with the access handler.
 *
 * @see \Drupal\projects\Access\ProjectEntityAccess
 */
class ProjectQueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * Leaves out archived projects and hidden ones of other organizations.
   */
  public function onQueryAccess(QueryAccessEvent $event): void {
    $account = $event->getAccount();
    $conditions = $event->getConditions();
    $conditions->addCacheContexts(['user.permissions']);

    // Administrators skip the conditions.
    if ($account->hasPermission('administer projects')) {
      return;
    }

    // The conditions of the event are an OR group: all of these must apply.
    $restrictions = (new ConditionGroup('AND'))->addCondition('status', 1);

    // The organization keeps listing its own hidden projects.
    if (!$account->hasPermission('view hidden projects')) {
      $restrictions->addCondition((new ConditionGroup('OR'))
        ->addCacheContexts(['user'])
        ->addCondition('hidden', 0)
        ->addCondition('uid', Profile::id($account)));
    }
    $conditions->addCondition($restrictions);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['entity.query_access.project' => 'onQueryAccess'];
  }

}
