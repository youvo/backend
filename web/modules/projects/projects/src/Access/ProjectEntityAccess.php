<?php

namespace Drupal\projects\Access;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;

/**
 * Access handler for project entities.
 *
 * @todo Translate role checks to permission checks and adjust caching, when
 *   dust has settled.
 */
class ProjectEntityAccess extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Get project for child entities.
    if ($entity instanceof ChildEntityInterface) {
      $entity = $entity->getOriginEntity();
    }

    // Only projects should be handled by this access controller.
    if (!$entity instanceof ProjectInterface) {
      throw new AccessException('The ProjectEntityAccess was called by an entity that is not a Project.');
    }

    // Administrators and supervisors skip access checks.
    if (
      in_array('supervisor', $account->getRoles()) ||
      in_array('administrator', $account->getRoles())
    ) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Unpublished projects are not accessible.
    // @todo Negotiate access handling in relation to hidden field.
    if (!$entity->isPublished()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // @todo Respect permissions when dust has settled.
    // Note that the access is governed by the related permissions. Therefore,
    // one should check the permissions first that are handled in the parent
    // method. Then, we revoke access depending on the status of the project.
    $access_result = new AccessResultNeutral();

    // Check access for edit action.
    if ($operation == 'view') {
      $access_result = $this->checkViewAccess($entity, $account);
    }

    // Check access for edit action.
    if ($operation == 'update') {
      $access_result = $this->checkEditAccess($entity, $account);
    }

    // Check access for delete action.
    if ($operation == 'delete') {
      $access_result = $this->checkDeleteAccess($entity, $account);
    }

    return $access_result;
  }

  /**
   * Helps to check access for view operation.
   */
  private function checkViewAccess(ProjectInterface $project, AccountInterface $account) {

    // Managers can view draft projects of organizations without managers.
    if (
      in_array('manager', $account->getRoles()) &&
      $project->lifecycle()->isDraft() &&
      !$project->hasManager()
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project->getOwner())
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    // Managers of the organization can view the project in any state.
    if (
      in_array('manager', $account->getRoles()) &&
      $project->isManager($account)
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project->getOwner())
        ->cachePerUser();
    }

    // The organization can view the project in any state.
    if ($project->isAuthor($account)) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Others can only view open, mediated or completed projects.
    if (
      $project->lifecycle()->isOpen() ||
      $project->lifecycle()->isOngoing() ||
      $project->lifecycle()->isCompleted()
    ) {
      return AccessResult::allowed()->addCacheableDependency($project);
    }

    return AccessResult::neutral();
  }

  /**
   * Helps to check access for edit operation.
   */
  private function checkEditAccess(ProjectInterface $project, AccountInterface $account) {

    // Managers of the organization can edit the project.
    if (
      in_array('manager', $account->getRoles()) &&
      $project->isManager($account)
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project->getOwner())
        ->cachePerUser();
    }

    // The organization can only edit draft, pending or open projects.
    if (
      $project->isAuthor($account) &&
      ($project->lifecycle()->isDraft() ||
      $project->lifecycle()->isPending() ||
      $project->lifecycle()->isOpen())
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    return AccessResult::neutral();
  }

  /**
   * Helps to check access for delete operation.
   */
  private function checkDeleteAccess(ProjectInterface $project, AccountInterface $account) {

    // Managers of the organization can delete the project.
    if (
      in_array('manager', $account->getRoles()) &&
      $project->isManager($account)
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project->getOwner())
        ->cachePerUser();
    }

    // The organization can only delete pending or draft projects.
    if (
      $project->isAuthor($account) &&
      ($project->lifecycle()->isPending() ||
      $project->lifecycle()->isDraft())
    ) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerUser();
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {

    // Organizations, Manager, Supervisors and Administrators can create
    // projects.
    if (
      in_array('organization', $account->getRoles()) ||
      in_array('manager', $account->getRoles()) ||
      in_array('supervisor', $account->getRoles()) ||
      in_array('administrator', $account->getRoles())
    ) {
      return AccessResult::allowed()->cachePerUser();
    }

    return AccessResult::neutral();
  }

}
