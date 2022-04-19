<?php

namespace Drupal\organizations;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;

/**
 * Access controller for the Organization entity.
 */
class OrganizationAccessControlHandler {

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

  /**
   * Checks create access.
   *
   * Only administrators should use the organization creation via admin form.
   * The creation of an organization is implemented in the following.
   * See \Drupal\organizations\Plugin\rest\resource\OrganizationCreateResource.
   *
   * @todo Maybe we can cover this case by permissions.
   */
  public static function checkCreateAccess() {
    return AccessResult::forbidden();
  }

}
