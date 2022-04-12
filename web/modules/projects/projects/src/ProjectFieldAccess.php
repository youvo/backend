<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Entity\Project;
use Drupal\user_types\Utility\Profiler;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the project bundle.
 *
 * @todo Maybe introduce permissions and cache per permissions when dust has
 *   settled.
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
    'field_result_files',
    'field_result_text'
  ];

  const USER_STATUS_FIELDS = [
    'user_is_applicant',
    'user_is_participant',
    'user_is_manager'
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
    if (!$entity instanceof Project) {
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

    // Creatives may view the computed applied field for open projects.
    if ($operation == 'view' &&
      Profiler::isCreative($account) &&
      self::isFieldOfGroup($field, self::USER_STATUS_FIELDS)) {
      return AccessResult::neutral()->cachePerUser();
    }

    // Result fields for completed projects are handled downstream.
    if ($operation == 'view' &&
      $entity->workflowManager()->isCompleted() &&
      self::isFieldOfGroup($field, self::RESULT_FIELDS)) {
      return AccessResult::neutral();
    }

    // Authors and managers may view applicants for open projects.
    if ($operation == 'view' &&
      $entity->workflowManager()->isOpen() &&
      $field->getName() == self::APPLICANTS_FIELD &&
      $entity->isAuthorOrManager($account)) {
      return AccessResult::neutral()->cachePerUser();
    }

    // Authors and managers may view participants for ongoing projects. Note
    // that completed projects are handled above.
    if ($operation == 'view' &&
      $entity->workflowManager()->isOngoing() &&
      $field->getName() == self::PARTICIPANTS_FIELD &&
      $entity->isAuthorOrManager($account)) {
      return AccessResult::neutral()->cachePerUser();
    }

    return AccessResult::forbidden();
  }

}
