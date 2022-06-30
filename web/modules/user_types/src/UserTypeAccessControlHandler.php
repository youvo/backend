<?php

namespace Drupal\user_types;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\CreativeAccessControlHandler;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\organizations\OrganizationAccessControlHandler;
use Drupal\user\UserAccessControlHandler;

/**
 * Provides access checks for bundled user entities.
 */
class UserTypeAccessControlHandler extends UserAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Prevent deletion when entity is new.
    if ($operation == 'delete' && $entity->isNew()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // Handle access check downstream for administrators.
    if ($account->hasPermission('administer users')) {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Invoke access check for different user types.
    $access_result = new AccessResultNeutral();
    if ($entity instanceof Creative) {
      $access_result = CreativeAccessControlHandler::checkAccess($entity, $operation, $account);
    }
    if ($entity instanceof Organization) {
      $access_result = OrganizationAccessControlHandler::checkAccess($entity, $operation, $account);
    }

    // Also run the access checks for users.
    return $access_result
      ->andIf(parent::checkAccess($entity, $operation, $account));
  }

  /**
   * {@inheritdoc}
   *
   * The user creation/registration is handled through custom resources.
   *
   * @see \Drupal\organizations\Plugin\rest\resource\OrganizationCreateResource
   * @see \Drupal\creatives\Plugin\rest\resource\CreativeRegisterResource
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::forbidden();
  }

}
