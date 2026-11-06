<?php

namespace Drupal\user_types\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Hook implementations for passwords.
 */
class PasswordHooks {

  public function __construct(
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_entity_field_access().
   *
   * The password policies check passwords in forms and in the password
   * endpoint only. JSON:API would save any password, so it may not change
   * one.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($operation !== 'edit' || $field_definition->getTargetEntityTypeId() !== 'user' || $field_definition->getName() !== 'pass') {
      return AccessResult::neutral();
    }
    // JSON:API flags its routes with this default.
    $is_jsonapi = (bool) $this->routeMatch->getRouteObject()?->getDefault('_is_jsonapi');
    return AccessResult::forbiddenIf($is_jsonapi, 'Passwords are changed through the password endpoint.')
      ->addCacheContexts(['route']);
  }

}
