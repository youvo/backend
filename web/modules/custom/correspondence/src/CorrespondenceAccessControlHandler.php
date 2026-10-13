<?php

namespace Drupal\correspondence;

use Drupal\child_entities\ChildEntityAccessControlHandler;
use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for correspondence entities, which are manager-only.
 */
class CorrespondenceAccessControlHandler extends ChildEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {

    if ($operation === 'view') {
      $result = AccessResult::allowedIfHasPermission($account, 'view correspondence');
      if ($result->isAllowed() && $entity instanceof ChildEntityInterface) {
        $result = $result->andIf($this->entityTypeManager
          ->getAccessControlHandler($entity->getParentEntityTypeId())
          ->access($entity->getParentEntity(), 'view', $account, TRUE));
      }
      return $result->addCacheableDependency($entity);
    }

    if (in_array($operation, ['update', 'delete'], TRUE)) {
      return AccessResult::allowedIfHasPermission($account, 'administer correspondence')
        ->addCacheableDependency($entity);
    }

    return AccessResult::neutral()->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer correspondence');
  }

}
