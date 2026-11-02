<?php

namespace Drupal\logbook;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {

    // Only logs should be handled by this access controller.
    if (!$entity instanceof LogInterface) {
      throw new AccessException('The LogAccessControlHandler was called by an entity that is not a Log.');
    }

    // Administrators skip the access checks.
    if ($account->hasPermission('administer log pattern')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Archived logs are only accessible with the permission.
    if ($entity->isArchived() && !$account->hasPermission('view archived log')) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    // Disabled logs are not accessible.
    if (!$entity->getPattern()->isEnabled()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity->getPattern())
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    // Check access for public log. A hidden project keeps its logs private.
    $project = $entity->getProject();
    if (
      $operation === 'view' &&
      $account->hasPermission('view public log') &&
      $entity->getPattern()->isPublic() &&
      !$project?->isHidden()
    ) {
      $access_result = AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern())
        ->addCacheableDependency($entity);
      return $project ? $access_result->addCacheableDependency($project) : $access_result;
    }

    // Check access for detectable log.
    if (
      $operation === 'view' &&
      $account->hasPermission('view detectable log') &&
      $entity->getPattern()->isDetectable()
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern())
        ->addCacheableDependency($entity);
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
          ->addCacheableDependency($entity)
          ->cachePerPermissions()
          ->cachePerUser();
      }

      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity->getPattern())
        ->addCacheableDependency($entity);
    }

    $access_result = AccessResult::neutral()
      ->addCacheableDependency($entity->getPattern())
      ->addCacheableDependency($entity)
      ->cachePerPermissions();
    return $project ? $access_result->addCacheableDependency($project) : $access_result;
  }

}
