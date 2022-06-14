<?php

namespace Drupal\logbook;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access handler for log event entities.
 */
class LogEventAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Only projects should be handled by this access controller.
    if (!$entity instanceof LogEventInterface) {
      throw new AccessException('The LogEventAccessControlHandler was called by an entity that is not a LogEvent.');
    }

    // Administrators and supervisors skip access checks.
    if (in_array('administrator', $account->getRoles())) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Disabled log events are not accessible.
    if (!$entity->getPattern()->isEnabled()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for public event.
    if (
      $operation == 'view' &&
      $entity->getPattern()->isPublic() &&
      $account->hasPermission('view public log event')
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for detectable event.
    if (
      $operation == 'view' &&
      $entity->getPattern()->isDetectable() &&
      $account->hasPermission('view detectable log event')
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for observable event.
    // Respect the relationship between manager and organization. A manager can
    // view the events for their organizations.
    if (
      $operation == 'view' &&
      $entity->getPattern()->isObservable() &&
      $account->hasPermission('view observable log event')
    ) {
      if ($entity->hasOrganization()) {
        /** @var \Drupal\organizations\Entity\Organization $organization */
        $organization = $entity->getOrganization();
        return AccessResult::allowedIf($organization->isManager($account))
          ->addCacheableDependency($organization)
          ->addCacheableDependency($entity->getPattern())
          ->cachePerUser();
      }
      elseif ($entity->hasProject()) {
        /** @var \Drupal\organizations\Entity\Organization $organization */
        $organization = $entity->getProject()->getOwner();
        return AccessResult::allowedIf($organization->isManager($account))
          ->addCacheableDependency($organization)
          ->addCacheableDependency($entity->getPattern())
          ->cachePerUser();
      }
      else {
        return AccessResult::allowed()
          ->cachePerPermissions()
          ->addCacheableDependency($entity->getPattern());
      }
    }

    return new AccessResultNeutral();
  }

}
