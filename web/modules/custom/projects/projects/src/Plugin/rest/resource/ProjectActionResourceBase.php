<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\projects\ProjectInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides base class for project action resources.
 */
abstract class ProjectActionResourceBase extends ResourceBase {

  use ProjectResourceRoutesTrait;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Handles custom access logic for the resource.
   */
  abstract public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface;

}
