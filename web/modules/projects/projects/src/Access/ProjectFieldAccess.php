<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\user_types\Utility\Profile;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the project bundle.
 *
 * @todo Maybe introduce permissions and cache per permissions when dust has
 *   settled.
 * @todo Decide how to handle project results access.
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
    if (in_array('administrator', $account->getRoles())) {
      return AccessResult::neutral()->cachePerUser();
    }

    // Viewing public fields is handled downstream.
    if ($operation == 'view' &&
      self::isFieldOfGroup($field,
        array_merge(self::PUBLIC_FIELDS, self::UNRESTRICTED_FIELDS))
    ) {
      return AccessResult::neutral();
    }

    // Editing unrestricted fields is handled downstream.
    if ($operation == 'edit' &&
      self::isFieldOfGroup($field, self::UNRESTRICTED_FIELDS)) {
      return AccessResult::neutral();
    }

    // A manager can determine the organization when creating a project.
    if ($operation == 'edit' &&
      $entity->isManager($account) &&
      $field->getName() == 'uid' &&
      $entity->isNew()) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Creatives may view the computed applied field for open projects.
    if ($operation == 'view' &&
      Profile::isCreative($account) &&
      self::isFieldOfGroup($field, self::USER_STATUS_FIELDS)) {
      return AccessResult::neutral()->cachePerUser();
    }

    // Result fields for completed projects are handled downstream.
    if ($operation == 'view' &&
      $entity->lifecycle()->isCompleted() &&
      self::isFieldOfGroup($field, self::RESULT_FIELDS)) {
      return AccessResult::neutral();
    }

    // Authors and managers may view applicants for open projects.
    if ($operation == 'view' &&
      $entity->lifecycle()->isOpen() &&
      $field->getName() == self::APPLICANTS_FIELD &&
      $entity->isAuthorOrManager($account)) {
      return AccessResult::neutral()->cachePerUser();
    }

    // Authors and managers may view participants for ongoing projects. Note
    // that completed projects are handled above.
    if ($operation == 'view' &&
      $entity->lifecycle()->isOngoing() &&
      $field->getName() == self::PARTICIPANTS_FIELD &&
      $entity->isAuthorOrManager($account)) {
      return AccessResult::neutral()->cachePerUser();
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($entity)
      ->addCacheableDependency($entity->getOwner());
  }

}
