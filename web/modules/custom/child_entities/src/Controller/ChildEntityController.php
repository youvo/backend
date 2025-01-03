<?php

namespace Drupal\child_entities\Controller;

use Drupal\child_entities\ChildEntityEnsureTrait;
use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\Context\ChildEntityRouteContextTrait;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ChildEntityController.
 *
 * Returns responses for child entity routes.
 */
class ChildEntityController extends EntityController {

  use ChildEntityRouteContextTrait;
  use ChildEntityEnsureTrait;

  /**
   * {@inheritdoc}
   *
   * This is essentially a copy of the parent method, because it does not allow
   * to be easily extended. We ensure that the entity type indeed implements a
   * child entity and add the route parameters in the end.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addPage($entity_type_id): RedirectResponse|array {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    static::entityImplementsChildEntityInterface($entity_type);

    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $bundle_key = $entity_type->getKey('bundle');
    $bundle_entity_type_id = $entity_type->getBundleEntityType();
    $build = [
      '#theme' => 'entity_add_list',
      '#bundles' => [],
    ];

    if ($bundle_entity_type_id) {
      $bundle_argument = $bundle_entity_type_id;
      $bundle_entity_type = $this->entityTypeManager->getDefinition($bundle_entity_type_id);
      $bundle_entity_type_label = $bundle_entity_type->getSingularLabel();
      $build['#cache']['tags'] = $bundle_entity_type->getListCacheTags();

      // Build the message shown when there are no bundles.
      $link_text = $this->t('Add a new @entity_type.', ['@entity_type' => $bundle_entity_type_label]);
      $link_route_name = 'entity.' . $bundle_entity_type->id() . '.add_form';
      $build['#add_bundle_message'] = $this->t('There is no @entity_type yet. @add_link', [
        '@entity_type' => $bundle_entity_type_label,
        '@add_link' => Link::createFromRoute($link_text, $link_route_name)->toString(),
      ]);

      // Filter out the bundles the user doesn't have access to.
      $access_control_handler = $this->entityTypeManager->getAccessControlHandler($entity_type_id);
      foreach ($bundles as $bundle_name => $bundle_info) {
        $access = $access_control_handler->createAccess($bundle_name, NULL, [], TRUE);
        if (!$access->isAllowed()) {
          unset($bundles[$bundle_name]);
        }
        $this->renderer->addCacheableDependency($build, $access);
      }

      // Add descriptions from the bundle entities.
      $bundles = $this->loadBundleDescriptions($bundles, $bundle_entity_type);
    }
    else {
      $bundle_argument = $bundle_key;
    }

    // Add parents to route arguments.
    $route_arguments = [];
    $this->addParentRouteArguments($route_arguments, $entity_type);

    $form_route_name = 'entity.' . $entity_type_id . '.add_form';
    // Redirect if there's only one bundle available.
    if (count($bundles) === 1) {
      $bundle_names = array_keys($bundles);
      $bundle_name = reset($bundle_names);
      $route_arguments[$bundle_argument] = $bundle_name;
      return $this->redirect($form_route_name, $route_arguments);
    }
    // Prepare the #bundles array for the template.
    foreach ($bundles as $bundle_name => $bundle_info) {
      $route_arguments_per_bundle = $route_arguments;
      $route_arguments_per_bundle[$bundle_argument] = $bundle_name;
      $build['#bundles'][$bundle_name] = [
        'label' => $bundle_info['label'],
        'description' => $bundle_info['description'] ?? '',
        'add_link' => Link::createFromRoute($bundle_info['label'], $form_route_name, $route_arguments_per_bundle),
      ];
    }

    return $build;
  }

  /**
   * Appends the parent arguments.
   *
   * @param array $route_arguments
   *   The option parameters.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The child entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function addParentRouteArguments(array &$route_arguments, EntityTypeInterface $entity_type): void {
    // Add entity route arguments.
    $parent_argument = $entity_type->getKey('parent');
    $route_arguments[$parent_argument] = $this->getParentEntityFromRoute($parent_argument)->id();

    // If parent is another child append its parents.
    $parent_type = $this->entityTypeManager->getDefinition($entity_type->getKey('parent'));
    if ($parent_type->hasKey('parent')) {
      $this->addParentRouteArguments($route_arguments, $parent_type);
    }
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
