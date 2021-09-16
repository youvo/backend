<?php

namespace Drupal\child_entities\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes referencing children of parent.
 */
class ComputedChildrenField extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Computes the field value.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function computeValue() {
    // Fetch the parent and the desired target type.
    $parent = $this->getEntity();
    $target_type = $this->getSetting('target_type');

    // Query for children referencing the parent.
    $query = \Drupal::entityQuery($target_type)
      ->condition($parent->getEntityTypeId(), $parent->id());

    // Sort by weight if the field is available.
    $fields = \Drupal::service('entity_field.manager')
      ->getBaseFieldDefinitions($target_type);
    if (array_key_exists('weight', $fields)) {
      $query->sort('weight');
    }

    // Attach the query result to the list.
    $this->setValue(array_map(function ($id) {
      return ['target_id' => $id];
    }, $query->execute()));
  }

}
