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

    /** @var \Drupal\logbook\LogPatternInterface $pattern */
    $pattern = $this->entityType;

    // Disabled log events are not accessible.
    if (!$pattern->isEnabled()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // Check access for public event.
    if (
      $operation == 'view' &&
      $pattern->isPublic() &&
      $account->hasPermission('view public log event')
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($pattern);
    }

    // Check access for detectable event.
    if (
      $operation == 'view' &&
      $pattern->isDetectable() &&
      $account->hasPermission('view detectable log event')
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($pattern);
    }

    // Check access for observable event.
    // Respect the relationship between manager and organization. A manager can
    // view the events for their organizations.
    if (
      $operation == 'view' &&
      $pattern->isObservable() &&
      $account->hasPermission('view observable log event')
    ) {
      if ($entity->hasOrganization()) {
        /** @var \Drupal\organizations\Entity\Organization $organization */
        $organization = $entity->getOrganization();
        return AccessResult::allowedIf($organization->isManager($account))
          ->addCacheableDependency($organization)
          ->addCacheableDependency($entity)
          ->cachePerUser();
      }
      elseif ($entity->hasProject()) {
        /** @var \Drupal\organizations\Entity\Organization $organization */
        $organization = $entity->getProject()->getOwner();
        return AccessResult::allowedIf($organization->isManager($account))
          ->addCacheableDependency($organization)
          ->addCacheableDependency($entity)
          ->cachePerUser();
      }
      else {
        return AccessResult::allowed()
          ->cachePerPermissions()
          ->addCacheableDependency($pattern);
      }
    }

    return new AccessResultNeutral();
  }

}
