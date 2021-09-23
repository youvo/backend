<?php

namespace Drupal\child_entities\Routing;

use Drupal\child_entities\ChildEntityEnsureTrait;
use Drupal\child_entities\Controller\ChildEntityController;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Child Entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class ChildContentEntityHtmlRouteProvider extends AdminHtmlRouteProvider {

  use ChildEntityEnsureTrait;

  /**
   * {@inheritdoc}
   */
  public function getAddPageRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getAddPageRoute($entity_type)) {
      $route->setDefault('_controller', ChildEntityController::class . '::addPage');
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $this->entityImplementsChildEntityInterface($entity_type);

    $collection = parent::getRoutes($entity_type);
    $parent_type = \Drupal::entityTypeManager()->getDefinition($entity_type->getKey('parent'));
    if (!$parent_type->hasLinkTemplate('canonical') && !$parent_type->hasLinkTemplate('edit-form')) {
      throw new UnsupportedEntityTypeDefinitionException('The parent entity type ' . $parent_type->id() . ' does not have a canonical or edit route.');
    }

    foreach ($collection as $key => $route) {
      $option_parameters = $route->getOption('parameters');
      if (!is_array($option_parameters)) {
        $option_parameters = [];
      }
      $option_parameters[$entity_type->getKey('parent')] = [
        'type' => 'entity:' . $entity_type->getKey('parent'),
      ];
      $route->setOption('parameters', $option_parameters);
      // @todo Route definition is manual at the moment. Rework maybe.
      // $this->prepareWithParentEntities($route, $entity_type);
      $collection->add($key, $route);
    }
    return $collection;
  }

  /**
   * Prepare the Entity with its Parents.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The Route.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The Child Entity Type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function prepareWithParentEntities(Route $route, EntityTypeInterface $entity_type) {
    $parent_type = \Drupal::entityTypeManager()->getDefinition($entity_type->getKey('parent'));

    if ($parent_type->hasLinkTemplate('canonical')) {
      $link = $parent_type->getLinkTemplate('canonical');
    }
    else {
      $link = $parent_type->getLinkTemplate('edit-form');
    }

    $route->setPath($link . $route->getPath());

    $option_parameters = $route->getOption('parameters');
    if (!is_array($option_parameters)) {
      $option_parameters = [];
    }
    $option_parameters[$entity_type->getKey('parent')] = [
      'type' => 'entity:' . $entity_type->getKey('parent'),
    ];
    $route->setOption('parameters', $option_parameters);

    if ($parent_type->hasKey('parent')) {
      $this->prepareWithParentEntities($route, $parent_type);
    }
  }

}
