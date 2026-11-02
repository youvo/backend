<?php

namespace Drupal\feedback\Access;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\feedback\FeedbackInterface;
use Drupal\user_types\Utility\Profile;

/**
 * Access handler for feedback entities.
 */
class FeedbackEntityAccess extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {

    // Only feedbacks should be handled by this access controller.
    if (!$entity instanceof FeedbackInterface) {
      throw new AccessException('The FeedbackEntityAccess was called by an entity that is not a Feedback.');
    }

    // Feedback is never deleted, also not by administrators.
    if ($operation === 'delete') {
      return AccessResult::forbidden();
    }

    // Only the author edits the feedback, until it is completed.
    $is_open_author = (int) $entity->getOwnerId() === Profile::id($account) && !$entity->isCompleted();
    if ($operation === 'edit' || $operation === 'update') {
      return AccessResult::allowedIf($is_open_author)
        ->addCacheableDependency($entity)
        ->cachePerUser();
    }

    // Administrators view any feedback.
    if ($account->hasPermission('administer feedback')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // The author views the feedback until it is completed.
    $access_result = $operation === 'view'
      ? AccessResult::allowedIf($is_open_author)
      : AccessResult::neutral();

    return $access_result
      ->addCacheableDependency($entity)
      ->cachePerPermissions()
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    // Feedback is created through the REST resource.
    // See FeedbackCreateResource::post().
    return AccessResult::forbidden();
  }

}
