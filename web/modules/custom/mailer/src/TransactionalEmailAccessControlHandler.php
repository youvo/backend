<?php

namespace Drupal\mailer;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access handler for transactional email config entities.
 */
class TransactionalEmailAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    if (!$entity instanceof TransactionalEmailInterface) {
      throw new AccessException('The TransactionalEmailAccessControlHandler was called by an entity that is not a TransactionalEmail.');
    }

    if ($account->hasPermission($entity->getEntityType()->getAdminPermission())) {
      return AccessResult::allowed()->cachePerUser();
    }

    if ($operation == 'update' || $operation == 'edit') {
      return AccessResult::allowedIf($account->hasPermission('edit transactional emails'));
    }

    return new AccessResultNeutral();
  }

}
