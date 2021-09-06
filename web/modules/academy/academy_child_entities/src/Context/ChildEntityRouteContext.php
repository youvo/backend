<?php

namespace Drupal\academy_child_entities\Context;

use Drupal\academy_child_entities\ChildEntityInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Sets the parent entity as a context on parent routes.
 */
class ChildEntityRouteContext implements ContextProviderInterface {

  use ChildEntityRouteContextTrait;
  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructs a new ChildEntityRouteContext.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(RouteMatchInterface $current_route_match, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation) {
    $this->currentRouteMatch = $current_route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    // Create an optional context definition for child entities.
    $contexts = [];

    $child_entity_types = $this->getChildEntityTypes();

    foreach ($unqualified_context_ids as $unqualified_context_id) {
      if (array_key_exists($unqualified_context_id, $child_entity_types)) {
        $context_definition = EntityContextDefinition::fromEntityTypeId($child_entity_types[$unqualified_context_id])
          ->setRequired(FALSE);

        // Cache this context per group on the route.
        $cacheability = new CacheableMetadata();
        $cacheability->setCacheContexts(['route.' . $child_entity_types[$unqualified_context_id]]);

        // Create a context from the definition and retrieved.
        $context = new Context($context_definition, $this->getParentEntityFromRoute($child_entity_types[$unqualified_context_id]));
        $context->addCacheableDependency($cacheability);

        $contexts[$child_entity_types[$unqualified_context_id]] = $context;
      }
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $contexts = [];
    foreach ($this->getChildEntityTypes() as $parent_entity_type_id) {
      $parent_entity_type = $this->entityTypeManager->getDefinition($parent_entity_type_id);
      $contexts[$parent_entity_type_id] = EntityContext::fromEntityTypeId(
        $parent_entity_type_id,
        $this->t('@entity_type from URL', ['@entity_type' => $parent_entity_type->getSingularLabel()]));
    }

    return $contexts;
  }

  /**
   * Get the all the Entity Types that implement ChildEntityTrait.
   *
   * @return array
   *   The Entity Types that implement the ChildEntityTrait.
   */
  private function getChildEntityTypes() {
    $child_entity_types = [];

    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($definition->entityClassImplements(ChildEntityInterface::class)) {
        $child_entity_types[$definition->id()] = $definition->getKey('parent');
      }
    }
    return $child_entity_types;
  }

}
