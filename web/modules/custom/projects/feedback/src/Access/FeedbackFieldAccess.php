<?php

namespace Drupal\feedback\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\feedback\FeedbackInterface;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the feedback entity.
 */
class FeedbackFieldAccess extends FieldAccess {

  /**
   * Fields that are set by the backend and may not be edited by the author.
   *
   * The feedback is created with its author and project in
   * FeedbackCreateResource::post() and completed in Feedback::preSave(). Both
   * do not check field access.
   */
  const array RESTRICTED_FIELDS = [
    'author',
    'completed',
    'created',
    'project',
  ];

  /**
   * {@inheritdoc}
   */
  public static function checkFieldAccess(
    ContentEntityInterface $entity,
    string $operation,
    FieldDefinitionInterface $field,
    AccountInterface $account,
  ): AccessResultInterface {

    // Only feedback fields should be controlled by this class.
    if (!$entity instanceof FeedbackInterface) {
      return AccessResult::neutral();
    }

    // Viewing is not restricted here. Note that JSON:API requires view access
    // to accept unchanged values of restricted fields in a PATCH request.
    if ($operation !== 'edit' || !self::isFieldOfGroup($field, self::RESTRICTED_FIELDS)) {
      return AccessResult::neutral();
    }

    // Administrators pass through.
    if ($account->hasPermission('administer feedback types')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    return AccessResult::forbidden()->cachePerPermissions();
  }

}
