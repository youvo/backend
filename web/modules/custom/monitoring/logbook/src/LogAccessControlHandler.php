<?php

namespace Drupal\logbook;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {

    // Only projects should be handled by this access controller.
    if (!$entity instanceof LogInterface) {
      throw new AccessException('The LogAccessControlHandler was called by an entity that is not a Log.');
    }

    // Administrators and supervisors skip access checks.
    if (in_array('administrator', $account->getRoles(), TRUE)) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Disabled logs are not accessible.
    if (!$entity->getPattern()->isEnabled()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for public log.
    if (
      $operation === 'view' &&
      $account->hasPermission('view public log') &&
      $entity->getPattern()->isPublic()
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for detectable log.
    if (
      $operation === 'view' &&
      $account->hasPermission('view detectable log') &&
      $entity->getPattern()->isDetectable()
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern());
    }

    // Check access for observable log.
    // Respect the relationship between manager and organization. A manager can
    // view the logs for their organizations.
    if (
      $operation === 'view' &&
      $account->hasPermission('view observable log') &&
      $entity->getPattern()->isObservable()
    ) {
      if ($entity->hasProject()) {
        return AccessResult::allowedIf($entity->getProject()->getOwner()->isManager($account))
          ->addCacheableDependency($entity->getProject()->getOwner())
          ->addCacheableDependency($entity->getPattern())
          ->cachePerUser();
      }

      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern());
    }

    return new AccessResultNeutral();
  }

}
