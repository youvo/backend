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
    return AccessResult::neutral();
  }

}
