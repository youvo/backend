<?php

namespace Drupal\child_entities;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the paragraph's entity.
 *
 * @see \Drupal\child_entities\ChildEntityTrait.
 */
class ChildEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Allowed when the operation is not view or the status is true.
    /** @var \Drupal\child_entities\ChildEntityInterface $entity */

    if ($operation == 'delete' && $entity->isNew()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    if ($admin_permission = $this->entityType->getAdminPermission()) {
      $access_result = AccessResult::allowedIfHasPermission($account, $admin_permission);
    }
    else {
      $access_result = AccessResult::neutral();
    }

    if ($entity->getParentEntity() != NULL) {
      $parent_access = $entity->getParentEntity()->access($operation, $account, TRUE);
      $access_result = $access_result->andIf($parent_access);
    }
    else {
      throw new AccessException('Could not resolve parent access handler.');
    }

    return $access_result;
  }

}
