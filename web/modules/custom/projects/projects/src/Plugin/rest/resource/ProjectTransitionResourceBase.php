<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides base class for project transition resources.
 *
 * @todo Use enums for transition constant starting with PHP 8.2.
 */
abstract class ProjectTransitionResourceBase extends ResourceBase {

  protected const TRANSITION = 'undefined';

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The route provider.
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->routeProvider = $container->get('router.route_provider');
    return $instance;
  }

  /**
   * Handles custom access logic for the resource.
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    $transition = static::TRANSITION;

    // The user may be permitted to bypass access control.
    $bybass_permission = WorkflowPermissions::bypassTransition($workflow_id);
    if ($account->hasPermission($bybass_permission)) {
      return AccessResult::allowed()->addCacheContexts(['user.permission']);
    }

    // The resource should define transition-specific access conditions.
    if (!static::projectAccessCondition($account, $project)) {
      return AccessResult::forbidden()->addCacheableDependency($project);
    }

    // The transition may be allowed if the user has the permission and the
    // project can perform the given transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, $transition);
    $access_condition = $project->isPublished() &&
      $project->lifecycle()->canTransition(ProjectTransition::from($transition)) &&
      $account->hasPermission($permission);

    return AccessResult::allowedIf($access_condition)
      ->addCacheableDependency($project)
      ->addCacheContexts(['user.permission']);
  }

  /**
   * Defines project-dependent transition-specific access condition.
   */
  abstract protected static function projectAccessCondition(AccountInterface $account, ProjectInterface $project): bool;

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {

    $collection = new RouteCollection();

    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->getPluginId());

    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', static::class . '::access');
      $route->addOptions([
        'parameters' => [
          'project' => [
            'type' => 'entity:project',
            'converter' => 'paramconverter.uuid',
          ],
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
