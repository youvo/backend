<?php

namespace Drupal\projects;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;
use Drupal\projects\Entity\Project;

/**
 * Access handler for project entities.
 *
 * See projects module file for hook.
 */
class ProjectEntityAccess extends NodeAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $node, $operation, AccountInterface $account) {

    // Only projects should be handled by this access controller.
    if (!$node instanceof Project) {
      parent::checkAccess($node, $operation, $account);
    }

    parent::checkAccess($node, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {

    // Only projects should be handled by this access controller.
    if ($entity_bundle != 'project') {
      return parent::checkCreateAccess($account, $context, $entity_bundle);
    }

    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

}
