<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;


/**
 * Provides field access methods for the project bundle.
 */
class ProjectFieldAccess {

  /**
   * Static call for the hook to check field access.
   *
   * @see projects.module projects_entity_field_access()
   */
  public static function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    if (self::isFieldPublic($field_definition)) {
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
  public static function isFieldUnrestricted(FieldDefinitionInterface|string $field) {

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
  public static function isFieldPublic(FieldDefinitionInterface|string $field) {

    // Resolve field name.
    $field_name = $field instanceof FieldDefinitionInterface ?
      $field->getName() : $field;

    // An unrestricted field is public.
    if (self::isFieldUnrestricted($field_name)) {
      return TRUE;
    }

    $public_fields = [
      'applied',
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

}
