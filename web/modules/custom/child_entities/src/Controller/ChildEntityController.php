<?php

namespace Drupal\child_entities\Controller;

use Drupal\child_entities\ChildEntityEnsureTrait;
use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ChildEntityController.
 *
 * Returns responses for child entity routes.
 */
class ChildEntityController extends EntityController {

  use ChildEntityEnsureTrait;

  /**
   * {@inheritdoc}
   *
   * We ensure that the entity type indeed implements a child entity. Core
   * redirects with the current route parameters if there is only one bundle,
   * but omits them in the links of the bundle list. Therefore, we add the
   * parent route parameters to these links.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addPage($entity_type_id, ?Request $request = NULL): RedirectResponse|array {

    static::entityImplementsChildEntityInterface($this->entityTypeManager->getDefinition($entity_type_id));

    $build = parent::addPage($entity_type_id, $request);

    if (is_array($build)) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      foreach ($build['#bundles'] as $bundle) {
        $url = $bundle['add_link']->getUrl();
        $url->setRouteParameters($url->getRouteParameters() + $route_parameters);
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function editTitle(RouteMatchInterface $route_match, ?EntityInterface $_entity = NULL): ?string {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return (string) $entity->label();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetEntity(RouteMatchInterface $route_match, ?EntityInterface $_entity = NULL): ?EntityInterface {

    // Looking for the matching entity in the route parameters.
    // The entity routes follow the pattern entity.{entity_id}.edit_form!
    $route_name = explode('.', $route_match->getRouteName());
    $parameters = $route_match->getParameters()->all();
    if (array_key_exists($route_name[1], $parameters)) {
      $candidate = $parameters[$route_name[1]];
      if ($candidate instanceof ChildEntityInterface) {
        $_entity = $candidate;
      }
    }

    return parent::doGetEntity($route_match, $_entity);
  }

}
