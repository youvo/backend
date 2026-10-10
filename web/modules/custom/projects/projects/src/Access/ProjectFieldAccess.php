<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
    AccountInterface $account,
  ): AccessResultInterface {

    // Only project fields should be controlled by this class.
    if (!$entity instanceof ProjectInterface) {
      return AccessResult::neutral();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer projects')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Viewing public fields is handled downstream. Note that all results from
    // here on depend on the permissions of the account.
    if ($operation === 'view' &&
      self::isFieldOfGroup($field, array_merge(self::PUBLIC_FIELDS, self::UNRESTRICTED_FIELDS))
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Editing unrestricted fields is handled downstream.
    if ($operation === 'edit' &&
      self::isFieldOfGroup($field, self::UNRESTRICTED_FIELDS)
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // A manager can determine the organization when creating a project.
    if ($operation === 'edit' &&
      $entity->isNew() &&
      $field->getName() === self::OWNER_FIELD &&
      $entity->getOwner()->isManager($account)
    ) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser();
    }

    // Creatives may view the computed status fields.
    if ($operation === 'view' &&
      $account->hasPermission('general creative access') &&
      self::isFieldOfGroup($field, self::USER_STATUS_FIELDS)
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Result fields for completed projects are handled downstream.
    if ($operation === 'view' &&
      self::isFieldOfGroup($field, self::RESULT_FIELDS) &&
      $entity->lifecycle()->isCompleted()
    ) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    // Authors and managers may view applicants for open projects. Archived
    // projects retain the applicants of the state they were archived from.
    if ($operation === 'view' &&
      $field->getName() === self::APPLICANTS_FIELD &&
      ($entity->lifecycle()->isOpen() || $entity->lifecycle()->isArchived()) &&
      ($entity->isAuthor($account) || $entity->getOwner()->isManager($account))
    ) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($entity->getOwner())
        ->cachePerPermissions()
        ->cachePerUser();
    }

    // Authors and managers may view participants for ongoing projects. Note
    // that completed projects are handled above. Archived projects retain the
    // participants of the state they were archived from.
    if ($operation === 'view' &&
      $field->getName() === self::PARTICIPANTS_FIELD &&
      ($entity->lifecycle()->isOngoing() || $entity->lifecycle()->isArchived()) &&
      ($entity->isAuthor($account) || $entity->getOwner()->isManager($account))
    ) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($entity->getOwner())
        ->cachePerPermissions()
        ->cachePerUser();
    }

    // Only these fields may be granted depending on the identity of the
    // account, e.g. the author or manager status. All other fields are denied
    // for any account that does not administer projects.
    $access_result = AccessResult::forbidden()
      ->addCacheableDependency($entity)
      ->addCacheableDependency($entity->getOwner())
      ->cachePerPermissions();
    if (in_array($field->getName(), [self::APPLICANTS_FIELD, self::PARTICIPANTS_FIELD, self::OWNER_FIELD], TRUE)) {
      $access_result->cachePerUser();
    }
    return $access_result;
  }

}
