<?php

namespace Drupal\projects\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Routing\ProjectHtmlRouteProvider;
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
    'field_lifecycle',
    'hidden',
    'langcode',
    'promote',
    'status',
    'sticky',
    'uid',
  ];

  /**
   * Fields the project manager page reads that are denied by default.
   *
   * These may be viewed by accounts with the permission 'view project manager
   * fields'. The list is derived from the view project_manager: 'id' is used
   * in all row triggers (data-project-id) and 'changed' is the logbook
   * trigger. Do not add fields that the page does not read.
   */
  const MANAGER_FIELDS = [
    'changed',
    'id',
  ];

  const MANAGER_FIELDS_PERMISSION = 'view project manager fields';

  /**
   * Fields only logged-in users view.
   */
  const AUTHENTICATED_FIELDS = [
    'field_contact',
  ];

  /**
   * Fields only the actions and transitions change, and administrators.
   *
   * The owner is one of them once the project exists.
   */
  const ACTION_FIELDS = [
    'field_lifecycle',
    'field_lifecycle_history',
    'hidden',
    'promote',
    'status',
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
  const LIFECYCLE_HISTORY_FIELD = 'field_lifecycle_history';
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

    // Only administrators edit the fields of the actions and transitions,
    // also not those who administer projects.
    if ($operation === 'edit' &&
      (self::isFieldOfGroup($field, self::ACTION_FIELDS) || ($field->getName() === self::OWNER_FIELD && !$entity->isNew())) &&
      !$account->hasPermission(ProjectHtmlRouteProvider::PERMISSION)
    ) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer projects')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Accounts with the manager permission may view the fields that the project
    // manager page reads. Never grants editing and never other fields.
    if ($operation === 'view' &&
      self::isFieldOfGroup($field, self::MANAGER_FIELDS) &&
      $account->hasPermission(self::MANAGER_FIELDS_PERMISSION)
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Viewing public fields is handled downstream. Note that all results from
    // here on depend on the permissions of the account.
    if ($operation === 'view' &&
      self::isFieldOfGroup($field, array_merge(self::PUBLIC_FIELDS, self::UNRESTRICTED_FIELDS))
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Viewing the fields for logged-in users is handled downstream.
    if ($operation === 'view' &&
      self::isFieldOfGroup($field, self::AUTHENTICATED_FIELDS) &&
      $account->hasPermission('view project')
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

    // Authors and managers may view applicants for open projects.
    if ($operation === 'view' &&
      $field->getName() === self::APPLICANTS_FIELD &&
      $entity->lifecycle()->isOpen() &&
      ($entity->isAuthor($account) || $entity->getOwner()->isManager($account))
    ) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($entity->getOwner())
        ->cachePerPermissions()
        ->cachePerUser();
    }

    // Authors and managers may view participants for ongoing projects. Note
    // that completed projects are handled above.
    if ($operation === 'view' &&
      $field->getName() === self::PARTICIPANTS_FIELD &&
      $entity->lifecycle()->isOngoing() &&
      ($entity->isAuthor($account) || $entity->getOwner()->isManager($account))
    ) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($entity->getOwner())
        ->cachePerPermissions()
        ->cachePerUser();
    }

    // Managers of the organization may view the lifecycle history.
    if ($operation === 'view' &&
      $field->getName() === self::LIFECYCLE_HISTORY_FIELD &&
      $account->hasPermission('general manager access') &&
      $entity->getOwner() instanceof Organization &&
      $entity->getOwner()->isManager($account)
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
    // For managers, the lifecycle history depends on whether they manage the
    // organization. For everyone else, it depends on the permissions only.
    if ($field->getName() === self::LIFECYCLE_HISTORY_FIELD && $account->hasPermission('general manager access')) {
      $access_result->cachePerUser();
    }
    return $access_result;
  }

  /**
   * Checks the view access to a field of projects in general.
   *
   * Filters and sorts are checked this way. Without it, a filter on the
   * applicants reveals where a creative applied. The rules that depend on the
   * project, like the author or the lifecycle state, can't grant access here.
   */
  public static function checkFieldAccessWithoutEntity(
    string $operation,
    FieldDefinitionInterface $field,
    AccountInterface $account,
  ): AccessResultInterface {

    if ($operation !== 'view') {
      return AccessResult::neutral();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer projects')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Accounts with the manager permission may view the fields that the project
    // manager page reads.
    if (self::isFieldOfGroup($field, self::MANAGER_FIELDS) &&
      $account->hasPermission(self::MANAGER_FIELDS_PERMISSION)
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Viewing public fields is handled downstream.
    if (self::isFieldOfGroup($field, array_merge(self::PUBLIC_FIELDS, self::UNRESTRICTED_FIELDS))) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // The UUID is the resource ID that JSON:API shows on every project.
    if ($field->getName() === 'uuid') {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Viewing the fields for logged-in users is handled downstream.
    if (self::isFieldOfGroup($field, self::AUTHENTICATED_FIELDS) &&
      $account->hasPermission('view project')
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Creatives may view the computed status fields.
    if (self::isFieldOfGroup($field, self::USER_STATUS_FIELDS) &&
      $account->hasPermission('general creative access')
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Creatives may filter by their own applications and participations for
    // their dashboard. The membership filter subscriber refuses any filter or
    // sort on these fields that is not for the creative themselves.
    if (in_array($field->getName(), [self::APPLICANTS_FIELD, self::PARTICIPANTS_FIELD], TRUE) &&
      $account->hasPermission('general creative access')
    ) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // Applicants, participants and results depend on the project.
    return AccessResult::forbidden()->cachePerPermissions();
  }

}
