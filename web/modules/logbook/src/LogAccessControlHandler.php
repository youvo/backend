<?php

namespace Drupal\logbook;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access handler for log entities.
 */
class LogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Only projects should be handled by this access controller.
    if (!$entity instanceof LogInterface) {
      throw new AccessException('The LogAccessControlHandler was called by an entity that is not a Log.');
    }

    // Administrators and supervisors skip access checks.
    if (in_array('administrator', $account->getRoles())) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Disabled logs are not accessible.
    if (!$entity->getPattern()->isEnabled()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for public log.
    if (
      $operation == 'view' &&
      $entity->getPattern()->isPublic() &&
      $account->hasPermission('view public log')
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for detectable log.
    if (
      $operation == 'view' &&
      $entity->getPattern()->isDetectable() &&
      $account->hasPermission('view detectable log')
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for observable log.
    // Respect the relationship between manager and organization. A manager can
    // view the logs for their organizations.
    if (
      $operation == 'view' &&
      $entity->getPattern()->isObservable() &&
      $account->hasPermission('view observable log')
    ) {
      if ($entity->hasOrganization()) {
        return AccessResult::allowedIf($entity->getOrganization()->isManager($account))
          ->addCacheableDependency($entity->getOrganization())
          ->addCacheableDependency($entity->getPattern())
          ->cachePerUser();
      }
      elseif ($entity->hasProject()) {
        return AccessResult::allowedIf($entity->getProject()->getOwner()->isManager($account))
          ->addCacheableDependency($entity->getProject()->getOwner())
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
