<?php

namespace Drupal\child_entities\Routing;

use Drupal\child_entities\ChildEntityEnsureTrait;
use Drupal\child_entities\Controller\ChildEntityController;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
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
    foreach ($collection as $key => $route) {
      if (strpos($key, 'edit_form')) {
        $route->setDefault('_title_callback', ChildEntityController::class . '::editTitle');
      }
      $option_parameters = $route->getOption('parameters');
      if (!is_array($option_parameters)) {
        $option_parameters = [];
      }
      $this->appendParentOptionParameters($option_parameters, $entity_type);
      $route->setOption('parameters', $option_parameters);
      $collection->add($key, $route);
    }
    return $collection;
  }

  /**
   * Appends the option parameters with parent entities.
   *
   * @param array $option_parameters
   *   The option parameters.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The child entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function appendParentOptionParameters(array &$option_parameters, EntityTypeInterface $entity_type): void {
    // Add entity option parameters.
    $option_parameters[$entity_type->getKey('parent')] = [
      'type' => 'entity:' . $entity_type->getKey('parent'),
    ];

    // If parent is another child append its parents.
    $parent_type = $this->entityTypeManager->getDefinition($entity_type->getKey('parent'));
    if ($parent_type->hasKey('parent')) {
      $this->appendParentOptionParameters($option_parameters, $parent_type);
    }
  }

}
