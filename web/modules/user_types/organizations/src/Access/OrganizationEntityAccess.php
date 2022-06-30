<?php

namespace Drupal\organizations\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;

/**
 * Access controller for the Organization entity.
 */
class OrganizationEntityAccess {

  /**
   * Checks access.
   *
   * See \Drupal\user_types\UserTypeAccessControlHandler::checkAccess().
   */
  public static function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Only organizations should be handled by this handler.
    if (!$entity instanceof Organization) {
      return AccessResult::neutral();
    }

    // Explicitly allow managers and owners of organizations to edit the account
    // of the organization. This ability will be narrowed down to certain fields
    // in the field access handler.
    // See \Drupal\organizations\OrganizationFieldAccess.
    if ($operation == 'edit') {
      if ($entity->isOwnerOrManager($account)) {
        return AccessResult::allowed()->cachePerUser();
      }
      return AccessResult::forbidden()->cachePerUser();
    }

    // Only managers can access prospect organizations.
    if ($entity->hasRoleProspect() &&
      !in_array('manager', $account->getRoles())) {
      return AccessResult::forbidden()->cachePerUser();
    }

    return AccessResult::allowed();
  }

}
