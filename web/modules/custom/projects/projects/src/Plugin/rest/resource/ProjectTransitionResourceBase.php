<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides base class for project transition resources.
 *
 * @todo Use enums for transition constant starting with PHP 8.2.
 */
abstract class ProjectTransitionResourceBase extends ResourceBase {

  use ProjectResourceRoutesTrait;

  protected const TRANSITION = 'undefined';

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Handles custom access logic for the resource.
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    $transition = static::TRANSITION;
    $access_result = AccessResult::allowed();

    // The user may be permitted to bypass access control.
    $bybass_permission = WorkflowPermissions::bypassTransition($workflow_id);
    if ($account->hasPermission($bybass_permission)) {
      return $access_result->cachePerPermissions();
    }

    // The resource should define project-dependent access conditions.
    if (!static::projectAccessCondition($account, $project)) {
      $access_result = AccessResult::forbidden('The project conditions for this transition are not met.');
    }

    // The project should be able to perform the given transition.
    if (!$project->isPublished()) {
      $access_result = AccessResult::forbidden('The project is not ready for this transition.');
    }

    // The user may not have the permission to initiate this transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, $transition);
    if (!$account->hasPermission($permission)) {
      $access_result = AccessResult::forbidden('The user is not allowed to initiate this transition.');
    }

    return $access_result->addCacheableDependency($project)->cachePerUser();
  }

  /**
   * Defines project-dependent access condition.
   */
  abstract protected static function projectAccessCondition(AccountInterface $account, ProjectInterface $project): bool;

}
