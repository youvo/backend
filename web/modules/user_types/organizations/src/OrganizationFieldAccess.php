<?php

namespace Drupal\organizations;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the organization user bundle.
 *
 * The entity access governs field access.
 * @see \Drupal\organizations\OrganizationAccessControlHandler
 *
 * The method checkFieldAccess() is used for field access control, when viewing
 * or editing the organization through the JSON:API or the administration. Also,
 * we use constants of this class when creating an organization prospect.
 * @see \Drupal\organizations\Plugin\rest\resource\OrganizationCreateResource
 *
 * Note that some fields (mail, pass and manager) are edited through special
 * endpoints, which have separate access controllers.
 * @see \Drupal\organizations\Plugin\rest\resource\OrganizationManageResource
 * @see \Drupal\user_types\Plugin\rest\resource\UserUpdateEmailResource
 * @see \Drupal\user_types\Plugin\rest\resource\UserUpdatePasswordResource
 *
 * The field projects is a computed field.
 * @see \Drupal\projects\Plugin\Field\ComputedProjectReferenceFieldItemList
 *
 * Note that the default access result is allowed.
 * @see \Drupal\Core\Entity\EntityAccessControlHandler::checkFieldAccess()
 *
 * @todo Maybe introduce permissions and cache per permissions when the dust has
 *   settled.
 */
class OrganizationFieldAccess extends FieldAccess {

  const EDIT_OWNER_OR_MANAGER = [
    'field_about',
    'field_aim',
    'field_avatar',
    'field_budget',
    'field_causes',
    'field_city',
    'field_contact',
    'field_count_fulltime',
    'field_count_volunteer',
    'field_country',
    'field_name',
    'field_phone',
    'field_portfolio',
    'field_publicity',
    'field_reachability',
    'field_short_name',
    'field_street',
    'field_url',
    'field_zip',
  ];

  const VIEW_PUBLIC = [
    'created',
    'field_about',
    'field_aim',
    'field_avatar',
    'field_budget',
    'field_causes',
    'field_contact',
    'field_manager',
    'field_name',
    'field_portfolio',
    'field_publicity',
    'field_short_name',
    'field_url',
    'langcode',
    'projects',
    'uid',
  ];

  const VIEW_PRIVATE = [
    'field_city',
    'field_count_fulltime',
    'field_count_volunteer',
    'field_country',
    'field_phone',
    'field_reachability',
    'field_referral',
    'field_street',
    'field_zip',
    'mail',
    'name',
  ];

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
    if (!$entity instanceof Organization) {
      return AccessResult::neutral();
    }

    // Administrators and supervisors pass through. This also targets editing.
    if (in_array('administrator', $account->getRoles()) ||
      in_array('supervisor', $account->getRoles())) {
      return AccessResult::neutral()->cachePerUser();
    }

    // Viewing public fields is handled downstream.
    if ($operation == 'view' &&
      self::isFieldOfGroup($field, self::VIEW_PUBLIC)) {
      return AccessResult::neutral();
    }

    // Viewing private fields when owner or manager is handled downstream.
    if ($operation == 'view' &&
      self::isFieldOfGroup($field, self::VIEW_PRIVATE) &&
      $entity->isOwnedOrManagedBy($account)) {
      return AccessResult::neutral()->cachePerUser();
    }

    // Editing fields when owner or manager is handled downstream. Note that
    // edit permissions are handled by the organization access handler.
    if ($operation == 'edit' &&
      self::isFieldOfGroup($field, self::EDIT_OWNER_OR_MANAGER)) {
      return AccessResult::neutral();
    }

    return AccessResult::forbidden();
  }

}
