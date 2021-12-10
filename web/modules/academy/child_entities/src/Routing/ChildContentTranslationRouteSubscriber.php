<?php

namespace Drupal\child_entities\Routing;

use Drupal\child_entities\ChildEntityTrait;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for entity translation routes.
 */
class ChildContentTranslationRouteSubscriber extends RouteSubscriberBase {

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a ContentTranslationRouteSubscriber object.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ContentTranslationManagerInterface $content_translation_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->contentTranslationManager = $content_translation_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Alter routes for translatable entities.
    foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {

      // Concern about child entities.
      $original_class = $entity_type->getOriginalClass();
      if (in_array(ChildEntityTrait::class, class_uses($original_class))) {

        // Get routes for content translation.
        $routes = [
          $collection->get('entity.' . $entity_type_id . '.content_translation_overview'),
          $collection->get('entity.' . $entity_type_id . '.content_translation_add'),
          $collection->get('entity.' . $entity_type_id . '.content_translation_edit'),
          $collection->get('entity.' . $entity_type_id . '.content_translation_delete'),
        ];
        $routes = array_filter($routes);

        // Manipulate each route.
        foreach ($routes as $route) {

          // Get current parameters and setup iterable entity type variable.
          $child_entity_type = $entity_type;
          $parameters = $route->getOption('parameters');

          // Setup route parameters for all parents and grandparents.
          while ($child_entity_type->hasKey('parent')) {
            $parent_entity_type_id = $child_entity_type->getKey('parent');
            $parameters += [
              $parent_entity_type_id => [
                'type' => 'entity:' . $parent_entity_type_id,
              ],
            ];
            if ($this->entityTypeManager->hasDefinition($parent_entity_type_id)) {
              $child_entity_type = $this->entityTypeManager->getDefinition($parent_entity_type_id);
            }
            else {
              break;
            }
          }

          // Add augmented parameters to route.
          $route->setOption('parameters', $parameters);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    // Should run after ContentTranslationRouteSubscriber so the routes can
    // inherit altered routes for translation pages. Therefore, priority -215.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -215];
    return $events;
  }

}
