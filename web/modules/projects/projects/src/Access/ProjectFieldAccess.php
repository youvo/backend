<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the project bundle.
 */
class ProjectFieldAccess extends FieldAccess {

  const UNRESTRICTED_FIELDS = [
    'body',
    'field_allowance',
    'field_appreciation',
    'field_city',
    'field_deadline',
    'field_image',
    'field_image_copyright',
    'field_local',
    'field_material',
    'field_skills',
    'field_workload',
    'title',
    'project_result',
  ];

  const PUBLIC_FIELDS = [
    'created',
    'field_contact',
    'field_lifecycle',
    'langcode',
    'promote',
    'status',
    'sticky',
    'uid',
  ];

  const RESULT_FIELDS = [
    'project_result',
    'field_participants',
    'field_participants_tasks',
  ];

  const USER_STATUS_FIELDS = [
    'user_is_applicant',
    'user_is_participant',
    'user_is_manager',
  ];

  const APPLICANTS_FIELD = 'field_applicants';
  const PARTICIPANTS_FIELD = 'field_participants';
  const OWNER_FIELD = 'uid';

  /**
   * {@inheritdoc}
   */
  public static function checkFieldAccess(
    ContentEntityInterface $entity,
    string $operation,
    FieldDefinitionInterface $field,
    AccountInterface $account
  ) {

    // Only project fields should be controlled by this class.
    if (!$entity instanceof ProjectInterface) {
      return AccessResult::neutral();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer projects')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Viewing public fields is handled downstream.
    if ($operation == 'view' &&
      self::isFieldOfGroup($field,
        array_merge(self::PUBLIC_FIELDS, self::UNRESTRICTED_FIELDS))) {
      return AccessResult::neutral();
    }

    // Editing unrestricted fields is handled downstream.
    if ($operation == 'edit' &&
      self::isFieldOfGroup($field, self::UNRESTRICTED_FIELDS)) {
      return AccessResult::neutral();
    }

    // A manager can determine the organization when creating a project.
    if ($operation == 'edit' &&
      $entity->isNew() &&
      $entity->getOwner()->isManager($account) &&
      $field->getName() == self::OWNER_FIELD) {
      return AccessResult::allowed()
        ->cachePerUser();
    }

    // Creatives may view the computed status fields.
    if ($operation == 'view' &&
      $account->hasPermission('general creative access') &&
      self::isFieldOfGroup($field, self::USER_STATUS_FIELDS)) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Result fields for completed projects are handled downstream.
    if ($operation == 'view' &&
      $entity->lifecycle()->isCompleted() &&
      self::isFieldOfGroup($field, self::RESULT_FIELDS)) {
      return AccessResult::neutral()->addCacheableDependency($entity);
    }

    // Authors and managers may view applicants for open projects.
    if ($operation == 'view' &&
      $entity->lifecycle()->isOpen() &&
      $field->getName() == self::APPLICANTS_FIELD &&
      ($entity->isAuthor($account) || $entity->getOwner()->isManager($account))) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($entity->getOwner())
        ->cachePerUser();
    }

    // Authors and managers may view participants for ongoing projects. Note
    // that completed projects are handled above.
    if ($operation == 'view' &&
      $entity->lifecycle()->isOngoing() &&
      $field->getName() == self::PARTICIPANTS_FIELD &&
      ($entity->isAuthor($account) || $entity->getOwner()->isManager($account))) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($entity->getOwner())
        ->cachePerUser();
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($entity)
      ->addCacheableDependency($entity->getOwner());
  }

}
