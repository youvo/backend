<?php

namespace Drupal\creatives;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the creative user bundle.
 */
class CreativeFieldAccess extends FieldAccess {

  /**
   * {@inheritdoc}
   */
  public static function checkFieldAccess(
    ContentEntityInterface $entity,
    string $operation,
    FieldDefinitionInterface $field_definition,
    AccountInterface $account
  ) {

    // Only project fields should be controlled by this class.
    if (!$entity instanceof Creative) {
      return AccessResult::forbidden();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer site')) {
      return AccessResult::neutral();
    }

    // @todo Placeholder! Introduce field dependent access decisions.
    if ($operation == 'view' || $operation == 'edit') {
      return AccessResult::neutral();
    }

    return AccessResult::forbidden();
  }

}
