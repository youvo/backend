<?php

namespace Drupal\courses;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\courses\Entity\Course;

/**
 * Access controller for the Course entity.
 *
 * Note that the Course entity is the ("grand"-)parent of Lecture, Paragraph and
 * Question entities. All inherit the access handling from this controller. The
 * inheritence is defined in:
 *
 * @see \Drupal\child_entities\ChildEntityAccessControlHandler
 */
class CourseAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * Link the activities to the permissions. checkAccess() is called with the
   * $operation as defined in the Course entity annotation.
   *
   * This access handler is called by the children of Course.
   *
   * @see \Drupal\child_entities\ChildEntityAccessControlHandler
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Check if access handler suits Course descendants logic.
    if (!($entity instanceof Course || $entity instanceof ChildEntityInterface)) {
      throw new AccessException('The CourseAccessControlHandler was called by an entity that does not implement the ChildEntityInterface nor is a Course.');
    }

    // Prevent deletion when entity is new.
    if ($operation == 'delete' && $entity->isNew()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // Check the admin_permission as defined in Course entity annotation.
    $admin_permission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($admin_permission)) {
      return AccessResult::allowed();
    }

    // Return access result by permissions defined in permissions.yml.
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view courses'),
      'delete', 'update' => AccessResult::allowedIfHasPermission($account, 'manage courses'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist. It
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {

    // Check the admin_permission as defined in Course entity annotation.
    $admin_permission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($admin_permission)) {
      return AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermission($account, 'manage courses');
  }

}
