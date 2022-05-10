<?php

namespace Drupal\questionnaire;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\questionnaire\Entity\Question;
use Drupal\youvo\Utility\FieldAccess;

/**
 * Provides field access methods for the question entity.
 */
class QuestionFieldAccess extends FieldAccess {

  const ANSWER_FIELDS = [
    'answers',
    'explanation',
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

    // Only question fields should be controlled by this class.
    if (!$entity instanceof Question) {
      return AccessResult::neutral();
    }

    // Administrators and editors pass through.
    if ($account->hasPermission('manage courses')) {
      return AccessResult::neutral()->cachePerPermissions();
    }

    // @todo The access check for answer fields is deactivated at the moment.
    //   We need to figure out how to pass the context in which a question is
    //   loaded. Currently, the parent entity is attached to the entity as the
    //   paragraph. And the computed field for the evaluation only accumulates
    //   the questions within a course as references. Maybe we can use the view
    //   mode.
    // Restrict accessing the question answer fields when viewing questionnaire.
    // if ($entity->getParentEntity()->bundle() == 'questionnaire' &&
    // self::isFieldOfGroup($field, self::ANSWER_FIELDS)) {
    // return AccessResult::forbidden();
    // }
    return AccessResult::neutral();
  }

}
