<?php

namespace Drupal\projects\Access;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;

/**
 * Access handler for project entities.
 */
class ProjectEntityAccess extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {

    // Get project for child entities.
    if ($entity instanceof ChildEntityInterface) {
      $entity = $entity->getOriginEntity();
    }

    // Only projects should be handled by this access controller.
    if (!$entity instanceof ProjectInterface) {
      throw new AccessException('The ProjectEntityAccess was called by an entity that is not a Project.');
    }

    // Archived projects are read-only, except for administrators.
    if (
      ($operation === 'edit' || $operation === 'update') &&
      $entity->isArchived() &&
      !$account->hasPermission('access project admin forms')
    ) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    // Administrators skip the access checks.
    $admin_permission = $operation === 'delete' ? 'delete any project' : 'administer projects';
    if ($account->hasPermission($admin_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Archived projects are not accessible.
    if ($entity->isArchived()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->cachePerPermissions();
    }

    $access_result = match ($operation) {
      'view' => $this->checkViewAccess($entity, $account),
      'edit', 'update' => $this->checkEditAccess($entity, $account),
      'delete' => $this->checkDeleteAccess($entity, $account),
      default => AccessResult::neutral()->addCacheableDependency($entity),
    };

    return $access_result->cachePerPermissions();
  }

  /**
   * Helps to check access for view operation.
   */
  protected function checkViewAccess(ProjectInterface $project, AccountInterface $account): AccessResult {

    $organization = $project->getOwner();

    // Managers can view draft projects of organizations without managers. The
    // owner is an organization, unless an administrator owns the project.
    if (
      $organization instanceof Organization &&
      !$organization->hasManager() &&
      $account->hasPermission('general manager access') &&
      $project->lifecycle()->isDraft()
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($organization)
        ->addCacheableDependency($project);
    }

    // Managers of the organization can view the project in any state.
    if (
      $account->hasPermission('general manager access') &&
      $organization instanceof Organization &&
      $organization->isManager($account)
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($organization)
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    // The organization can view the project in any state.
    if ($project->isAuthor($account)) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    // Logged-in users can view open, mediated or completed projects, also
    // hidden ones by link.
    if (
      $account->hasPermission('view project') &&
      ($project->lifecycle()->isOpen() ||
      $project->lifecycle()->isOngoing() ||
      $project->lifecycle()->isCompleted())
    ) {
      return AccessResult::allowed()->addCacheableDependency($project);
    }

    // Others can only view open or completed projects that are not hidden.
    if (
      !$project->isHidden() &&
      ($project->lifecycle()->isOpen() ||
      $project->lifecycle()->isCompleted())
    ) {
      return AccessResult::allowed()->addCacheableDependency($project);
    }

    return AccessResult::neutral()
      ->addCacheableDependency($organization)
      ->addCacheableDependency($project)
      ->cachePerUser();
  }

  /**
   * Helps to check access for edit operation.
   */
  protected function checkEditAccess(ProjectInterface $project, AccountInterface $account): AccessResult {

    // Managers of the organization can edit the project.
    if (
      $account->hasPermission('general manager access') &&
      $project->getOwner()->isManager($account)
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project->getOwner())
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    // The organization can only edit draft, pending or open projects.
    if (
      $account->hasPermission('update own project') &&
      $project->isAuthor($account) &&
      ($project->lifecycle()->isDraft() ||
      $project->lifecycle()->isPending() ||
      $project->lifecycle()->isOpen())
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    return AccessResult::neutral()
      ->addCacheableDependency($project->getOwner())
      ->addCacheableDependency($project)
      ->cachePerUser();
  }

  /**
   * Helps to check access for delete operation.
   */
  protected function checkDeleteAccess(ProjectInterface $project, AccountInterface $account): AccessResult {

    // The organization can only delete draft or pending projects.
    if (
      $account->hasPermission('delete own project') &&
      $project->isAuthor($account) &&
      ($project->lifecycle()->isPending() ||
      $project->lifecycle()->isDraft())
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    return AccessResult::neutral()
      ->addCacheableDependency($project)
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {

    // Administrators skip the access checks.
    if ($account->hasPermission('administer projects')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return AccessResult::allowedIfHasPermission($account, 'create project');
  }

}
