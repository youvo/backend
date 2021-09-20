<?php

namespace Drupal\child_entities\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes referencing children of parent.
 */
class ComputedChildrenBaseField extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Computes the field value.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function computeValue() {
    // Fetch the parent and the desired target type.
    $parent = $this->getEntity();
    $target_type_id = $this->getSetting('target_type');

    // Query for children referencing the parent.
    $query = \Drupal::entityQuery($target_type_id)
      ->condition($parent->getEntityTypeId(), $parent->id());

    // Sort by weight if the field is available.
    $target_type = \Drupal::entityTypeManager()->getDefinition($target_type_id);
    if ($target_type->hasKey('weight')) {
      $query->sort($target_type->getKey('weight'));
    }

    // Attach the query result to the list.
    $this->setValue(array_map(fn ($id) => ['target_id' => $id], $query->execute()));
  }

}
