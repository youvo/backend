<?php

namespace Drupal\projects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;
use Drupal\projects\Entity\Project;

/**
 * Access handler for project entities.
 *
 * See projects module file for hook.
 *
 * @todo Translate role checks to permission checks and adjust caching, when
 *   dust has settled.
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

    // Note that the access is governed by the related permissions. Therefore,
    // one should check the permissions first that are handled in the parent
    // method. Then, we revoke access depending on the status of the project.
    $access_result = new AccessResultNeutral();

    // Check access for edit action.
    if ($operation == 'edit') {
      $access_result = $this->checkEditAccess($node, $account);
    }

    // Modify access for delete action.
    if ($operation == 'delete') {
      $access_result = $this->checkDeleteAccess($node, $account);
    }

    // Delegate permission check to node access handler.
    return $access_result
      ->orIf(parent::checkAccess($node, $operation, $account));
  }

  /**
   * Helps to check access for edit operation.
   */
  private function checkEditAccess(ProjectInterface $project, AccountInterface $account) {

    // Only managers of the organization can edit the project.
    if (in_array('manager', $account->getRoles())) {
      return AccessResult::forbiddenIf(!$project->isManager($account))
        ->addCacheableDependency($project->getOwner())
        ->cachePerUser();
    }

    // The organization can only edit draft, pending or open projects.
    if ($project->isAuthor($account)) {
      return AccessResult::forbiddenIf(
        !$project->isPublished() ||
        !($project->lifecycle()->isPending() ||
        $project->lifecycle()->isDraft() ||
        $project->lifecycle()->isOpen()))
        ->cachePerUser()
        ->addCacheableDependency($project);
    }

    return AccessResult::neutral();
  }

  /**
   * Helps to check access for delete operation.
   */
  private function checkDeleteAccess(ProjectInterface $project, AccountInterface $account) {

    // Only managers of the organization can delete the project.
    if (in_array('manager', $account->getRoles())) {
      return AccessResult::forbiddenIf(!$project->isManager($account))
        ->addCacheableDependency($project->getOwner())
        ->cachePerUser();
    }

    // The organization can only delete pending or draft projects.
    if ($project->isAuthor($account) &&
      !($project->lifecycle()->isPending() ||
        $project->lifecycle()->isDraft())) {
      return AccessResult::forbidden()
        ->cachePerUser()
        ->addCacheableDependency($project);
    }

    return AccessResult::neutral();
  }

}
