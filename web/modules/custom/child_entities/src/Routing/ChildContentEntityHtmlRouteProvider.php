<?php

namespace Drupal\child_entities\Routing;

use Drupal\child_entities\ChildEntityEnsureTrait;
use Drupal\child_entities\ChildEntityHierarchy;
use Drupal\child_entities\Controller\ChildEntityController;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for child entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class ChildContentEntityHtmlRouteProvider extends AdminHtmlRouteProvider {

  use ChildEntityEnsureTrait;

  /**
   * Constructs a ChildContentEntityHtmlRouteProvider object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    protected ChildEntityHierarchy $hierarchy,
  ) {
    parent::__construct($entity_type_manager, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('child_entities.hierarchy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAddPageRoute(EntityTypeInterface $entity_type): ?Route {
    if ($route = parent::getAddPageRoute($entity_type)) {
      $route->setDefault('_controller', ChildEntityController::class . '::addPage');
      return $route;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo Route path definition is manual at the moment. Rework maybe.
   */
  public function getRoutes(EntityTypeInterface $entity_type): RouteCollection|array {
    static::entityImplementsChildEntityInterface($entity_type);
    $collection = parent::getRoutes($entity_type);
    $ancestor_ids = $this->hierarchy->getAncestorEntityTypeIds($entity_type);
    foreach ($collection as $key => $route) {
      if (strpos($key, 'edit_form')) {
        $route->setDefault('_title_callback', ChildEntityController::class . '::editTitle');
      }
      $option_parameters = $route->getOption('parameters');
      if (!is_array($option_parameters)) {
        $option_parameters = [];
      }
      foreach ($ancestor_ids as $ancestor_id) {
        $option_parameters[$ancestor_id] = ['type' => 'entity:' . $ancestor_id];
      }
      $route->setOption('parameters', $option_parameters);
      $collection->add($key, $route);
    }
    return $collection;
  }

}
