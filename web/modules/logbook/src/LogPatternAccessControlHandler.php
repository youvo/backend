<?php

namespace Drupal\logbook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the log pattern entity type.
 */
class LogPatternAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return match ($operation) {
      'edit', 'update' => AccessResult::allowedIfHasPermission($account, 'edit log pattern'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'administer log pattern'),
      default => parent::checkAccess($entity, $operation, $account),
    };
  }

}
