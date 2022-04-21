<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
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
      return parent::checkAccess($node, $operation, $account);
    }

    // Check access for delete action.
    if ($operation == 'delete') {
      return $this->checkDeleteAccess($node, $account);
    }

    return parent::checkAccess($node, $operation, $account);
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

  /**
   * Helps to check access for delete operation.
   */
  private function checkDeleteAccess(ProjectInterface $project, AccountInterface $account) {

    // Supervisors and managers can delete projects.
    if (in_array('supervisor', $account->getRoles()) ||
      $project->isManager($account)) {
      return AccessResult::allowed();
    }

    // Managers and the organization can delete pending or draft projects.
    if (($project->workflowManager()->isPending() ||
        $project->workflowManager()->isDraft()) &&
      $project->isAuthorOrManager($account)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
