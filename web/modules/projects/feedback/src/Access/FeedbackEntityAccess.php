<?php

namespace Drupal\feedback\Access;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\feedback\FeedbackInterface;

/**
 * Access handler for feedback entities.
 */
class FeedbackEntityAccess extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Only feedbacks should be handled by this access controller.
    if (!$entity instanceof FeedbackInterface) {
      throw new AccessException('The FeedbackEntityAccess was called by an entity that is not a Feedback.');
    }

    // Administrators and supervisors skip access checks.
    // Discourage deleting feedbacks.
    if (
      in_array('supervisor', $account->getRoles()) ||
      in_array('administrator', $account->getRoles())
    ) {
      return AccessResult::allowedIf($operation != 'delete')->cachePerUser();
    }

    // Check access for view action.
    if ($operation == 'view') {
      return AccessResult::allowedIf($entity->getOwnerId() == $account->id())->cachePerUser();
    }

    // Check access for edit action.
    if ($operation == 'edit' || $operation == 'update') {
      return AccessResult::allowedIf($entity->getOwnerId() == $account->id())->cachePerUser();
    }

    // Check access for delete action.
    if ($operation == 'delete') {
      return AccessResult::forbidden();
    }

    return AccessResult::neutral()->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Feedback is created through the REST resource.
    // See FeedbackCreateResource::post().
    return AccessResult::forbidden();
  }

}
