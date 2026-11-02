<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\projects\ProjectInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides the base class for project action and transition resources.
 */
abstract class ProjectResourceBase extends ResourceBase {

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

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
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Checks the access to the resource for the project in the route.
   */
  abstract public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface;

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
