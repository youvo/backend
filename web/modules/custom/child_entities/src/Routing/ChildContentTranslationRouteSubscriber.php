<?php

namespace Drupal\child_entities\Routing;

use Drupal\child_entities\ChildEntityHierarchy;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for entity translation routes.
 */
class ChildContentTranslationRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs a ChildContentTranslationRouteSubscriber object.
   */
  public function __construct(
    protected ContentTranslationManagerInterface $contentTranslationManager,
    protected ChildEntityHierarchy $hierarchy,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function alterRoutes(RouteCollection $collection): void {

    // Alter routes for translatable entities.
    foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {

      // Concern about child entities.
      if ($this->hierarchy->isChildEntityType($entity_type)) {

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

          // Setup route parameters for all parents and grandparents.
          $parameters = $route->getOption('parameters') ?? [];
          foreach ($this->hierarchy->getAncestorEntityTypeIds($entity_type) as $ancestor_id) {
            $parameters += [$ancestor_id => ['type' => 'entity:' . $ancestor_id]];
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
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Should run after ContentTranslationRouteSubscriber so the routes can
    // inherit altered routes for translation pages. Therefore, priority -215.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -215];
    return $events;
  }

}
