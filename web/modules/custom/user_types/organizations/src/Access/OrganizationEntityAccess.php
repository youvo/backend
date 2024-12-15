<?php

namespace Drupal\organizations\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\user\UserInterface;

/**
 * Access controller for the Organization entity.
 */
class OrganizationEntityAccess {

  /**
   * Checks access.
   *
   * See \Drupal\user_types\UserTypeAccessControlHandler::checkAccess().
   */
  public static function checkAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {

    // Only organizations should be handled by this check.
    if (!$entity instanceof Organization) {
      return AccessResult::neutral();
    }

    // Explicitly allow managers and owners of organizations to edit the account
    // of the organization. This ability will be narrowed down to certain fields
    // in the field access handler.
    // See \Drupal\organizations\OrganizationFieldAccess.
    if ($operation === 'edit') {
      if ($entity->isOwnerOrManager($account)) {
        return AccessResult::allowed()
          ->addCacheableDependency($entity)
          ->cachePerUser();
      }
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->cachePerUser();
    }

    // Only managers can access prospect organizations.
    if ($entity->hasRoleProspect() &&
      !$account->hasPermission('general manager access')) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    return AccessResult::allowed()
      ->addCacheableDependency($entity);
  }

  /**
   * Checks access to manage organization.
   */
  public function accessManage(AccountInterface $account, ?UserInterface $organization = NULL): AccessResultInterface {
    // Only organizations should be handled by this check.
    if (!$organization instanceof Organization) {
      return AccessResult::neutral();
    }
    return AccessResult::allowedIf(in_array('manager', $account->getRoles(), TRUE));
  }

  /**
   * Checks access for organization create resource.
   */
  public function accessCreate(AccountInterface $account): AccessResultInterface {
    // Only anonymous user is allowed to create new organization.
    if ($account->isAnonymous()) {
      return AccessResult::allowed();
    }
    return AccessResult::neutral();
  }

}
