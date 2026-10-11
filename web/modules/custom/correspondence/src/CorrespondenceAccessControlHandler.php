<?php

namespace Drupal\correspondence;

use Drupal\child_entities\ChildEntityAccessControlHandler;
use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;

/**
 * Access controller for correspondence entities, which are manager-only.
 *
 * Viewing, and updating or deleting notes, requires the permission, view
 * access to the project and that the account is the assigned manager of the
 * project's organization (or has the permission to administer projects).
 * Creating a note is permission-based only, as the create access cannot know
 * the project: the routes and the manager mail access service check that the
 * account manages the project. Mails are updated, deleted and created with the
 * administer permission only.
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
        $result = $result->andIf($this->managerAccess($parent, $account));
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
   * Checks that the account manages the project.
   *
   * The same rule as for the manager mail access: administrators of projects
   * and the assigned manager of the project's organization.
   */
  protected function managerAccess(EntityInterface $parent, AccountInterface $account): AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'administer projects');
    if ($result->isAllowed() || !$parent instanceof ProjectInterface) {
      return $result->cachePerUser();
    }
    $owner = $parent->getOwner();
    return AccessResult::allowedIf($owner instanceof Organization && $owner->isManager($account))
      ->addCacheableDependency($parent)
      ->addCacheableDependency($owner)
      ->cachePerUser()
      ->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    $permission = $entity_bundle === 'note' ? 'manage correspondence notes' : 'administer correspondence';
    return AccessResult::allowedIfHasPermission($account, $permission);
  }

}
