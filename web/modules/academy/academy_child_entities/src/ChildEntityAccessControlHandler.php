<?php

namespace Drupal\academy_child_entities;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the paragraph's entity.
 *
 * @see \Drupal\academy_child_entities\ChildEntityTrait.
 */
class ChildEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Allowed when the operation is not view or the status is true.
    /** @var \Drupal\academy_child_entity\ChildEntityInterface $entity */

    if ($operation === 'view' && is_subclass_of($entity, EntityPublishedInterface::class)) {
      $access_result = AccessResult::allowedIf($entity->isPublished());
    }
    else {
      $access_result = AccessResult::allowed();
    }

    if ($entity->getParentEntity() != NULL) {
      $parent_access = $entity->getParentEntity()->access($operation, $account, TRUE);
      $access_result = $access_result->andIf($parent_access);
    }

    return $access_result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Allow paragraph entities to be created in the context of entity forms.
    if (\Drupal::requestStack()->getCurrentRequest()->getRequestFormat() === 'html') {
      return AccessResult::allowed()->addCacheContexts(['request_format']);
    }
    return AccessResult::neutral()->addCacheContexts(['request_format']);
  }

}
