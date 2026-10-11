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
    $is_note = $entity instanceof CorrespondenceInterface && $entity->isNote();

    if ($operation === 'view' || ($is_note && in_array($operation, ['update', 'delete'], TRUE))) {
      $permission = $operation === 'view' ? 'view correspondence' : 'manage correspondence notes';
      $result = AccessResult::allowedIfHasPermission($account, $permission);
      if ($result->isAllowed() && $entity instanceof ChildEntityInterface) {
        // A dangling or missing parent must not turn into an exception.
        try {
          $parent = $entity->getParentEntity();
        }
        catch (\RuntimeException) {
          return AccessResult::forbidden('The parent entity does not exist.')
            ->addCacheableDependency($entity)
            ->cachePerPermissions();
        }
        $result = $result->andIf($this->entityTypeManager
          ->getAccessControlHandler($entity->getParentEntityTypeId())
          ->access($parent, 'view', $account, TRUE));
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
    $permission = $entity_bundle === 'note' ? 'manage correspondence notes' : 'administer correspondence';
    return AccessResult::allowedIfHasPermission($account, $permission);
  }

}
