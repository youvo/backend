<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\creatives\Entity\Creative;

/**
 * Provides field access methods for the project bundle.
 */
class ProjectFieldAccess {

  /**
   * Static call for the hook to check field access.
   * @see projects.module projects_entity_field_access()
   */
  public static function checkFieldAccess(
    ProjectInterface $project,
    string $operation,
    FieldDefinitionInterface $field_definition,
    AccountInterface $account
  ) {

    // Administrators pass through.
    if ($account->hasPermission('administer site')) {
      return AccessResult::neutral();
    }

    // Viewing public fields is handled downstream.
    if ($operation == 'view' &&
      self::isPublicField($field_definition)) {
      return AccessResult::neutral();
    }

    // Editing unrestricted fields is handled downstream.
    if ($operation == 'edit' &&
      self::isUnrestrictedField($field_definition)) {
      return AccessResult::neutral();
    }

    // Creatives may view the computed applied field for open projects.
    if ($operation == 'view' &&
      self::isCreative($account) &&
      $field_definition->getName() == 'applied' &&
      $project->workflowManager()->isOpen()) {
      return AccessResult::neutral();
    }

    // Result fields for completed projects are handled downstream.
    if ($operation == 'view' &&
      $project->workflowManager()->isCompleted() &&
      self::isResultField($field_definition)) {
      return AccessResult::neutral();
    }

    // Authors and managers may view applicants for open projects.
    if ($operation == 'view' &&
      $project->workflowManager()->isOpen() &&
      $field_definition->getName() == 'field_applicants' &&
      $project->isAuthorOrManager($account)) {
      return AccessResult::neutral();
    }

    // Authors and managers may view participants for ongoing projects. Note
    // that completed projects are handled above.
    if ($operation == 'view' &&
      $project->workflowManager()->isOngoing() &&
      $field_definition->getName() == 'field_participants' &&
      $project->isAuthorOrManager($account)) {
      return AccessResult::neutral();
    }

    return AccessResult::forbidden();
  }

  /**
   * We hard-code unrestricted fields for projects here. This approach might
   * be less flexible and lead to confusion when adding new fields. But, it is
   * most secure when granting access to setting field values during creation
   * of new project entities. @see ProjectRestResponder
   */
  public static function isUnrestrictedField(FieldDefinitionInterface|string $field) {

    // Resolve field name.
    $field_name = $field instanceof FieldDefinitionInterface ?
      $field->getName() : $field;

    $unrestricted_fields = [
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

    return in_array($field_name, $unrestricted_fields);
  }

  /**
   * We hard-code public fields for projects here. See comment above.
   */
  public static function isPublicField(FieldDefinitionInterface|string $field) {

    // Resolve field name.
    $field_name = $field instanceof FieldDefinitionInterface ?
      $field->getName() : $field;

    // An unrestricted field is public.
    if (self::isUnrestrictedField($field_name)) {
      return TRUE;
    }

    $public_fields = [
      'created',
      'field_contact',
      'field_lifecycle',
      'langcode',
      'promote',
      'status',
      'sticky',
      'uid',
    ];

    return in_array($field_name, $public_fields);
  }

  /**
   * We hard-code result fields for projects here. See comment above.
   */
  public static function isResultField(FieldDefinitionInterface|string $field) {

    // Resolve field name.
    $field_name = $field instanceof FieldDefinitionInterface ?
      $field->getName() : $field;

    $result_fields = [
      'field_participants',
      'field_participants_tasks',
      'field_result_files',
      'field_result_text'
    ];

    return in_array($field_name, $result_fields);
  }

  /**
   * With different authorization methods the account object may be a
   * AccountProxy or a TokenAuthUser. Use this helper to determine whether
   * the account is a creative.
   */
  private static function isCreative(AccountInterface $account) {
    if ($account instanceof AccountProxyInterface) {
      $account = $account->getAccount();
      if (class_exists('Drupal\\simple_oauth\\Authentication\\TokenAuthUser') &&
        $account instanceof \Drupal\simple_oauth\Authentication\TokenAuthUser) {
        return $account->bundle() == 'user';
      }
      return $account instanceof Creative;
    }
    return $account instanceof Creative;
  }

}
