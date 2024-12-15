<?php

namespace Drupal\child_entities\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes referencing children of parent.
 *
 * @todo Use DI after https://www.drupal.org/project/drupal/issues/3294266
 */
class ComputedChildEntityReferenceFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Computes the field value.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function computeValue(): void {

    // Fetch the parent and the desired target type.
    $parent = $this->getEntity();
    $target_type_id = $this->getSetting('target_type');

    $entity_type_manager = \Drupal::entityTypeManager();

    // Query for children referencing the parent.
    $query = $entity_type_manager
      ->getStorage($target_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition($parent->getEntityTypeId(), $parent->id());

    // Sort by weight if the field is available.
    $target_type = $entity_type_manager->getDefinition($target_type_id);
    if ($target_type->hasKey('weight')) {
      $query->sort($target_type->getKey('weight'));
    }

    // Attach the query result to the list.
    $this->setValue(
      array_map(static fn ($id) => ['target_id' => $id], $query->execute())
    );
  }

}
