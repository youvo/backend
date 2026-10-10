<?php

namespace Drupal\child_entities\Hook;

use Drupal\child_entities\ChildEntityHierarchy;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the child entities module.
 */
class ChildEntityHooks {

  /**
   * Constructs a ChildEntityHooks object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ChildEntityHierarchy $hierarchy,
  ) {}

  /**
   * Implements hook_entity_predelete().
   *
   * Deletes the children of the entity if their entity type opts in with the
   * "cascade_delete" entity type property.
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {

    foreach ($this->hierarchy->getChildEntityTypes() as $child_type_id => $parent_type_id) {

      if ($parent_type_id !== $entity->getEntityTypeId()) {
        continue;
      }

      $child_type = $this->entityTypeManager->getDefinition($child_type_id);
      if (!$child_type->get('cascade_delete')) {
        continue;
      }

      $storage = $this->entityTypeManager->getStorage($child_type_id);
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($child_type->getKey('parent'), $entity->id())
        ->execute();
      if ($ids) {
        $storage->delete($storage->loadMultiple($ids));
      }
    }
  }

}
