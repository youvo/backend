<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides base class for project transition resources.
 */
abstract class ProjectTransitionResourceBase extends ResourceBase {

  use ProjectResourceRoutesTrait;

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
  abstract public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface;

}
