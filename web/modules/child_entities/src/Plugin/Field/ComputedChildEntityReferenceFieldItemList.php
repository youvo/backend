<?php

namespace Drupal\child_entities\Plugin\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes referencing children of parent.
 */
class ComputedChildEntityReferenceFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Computes the field value.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function computeValue() {

    // Fetch the parent and the desired target type.
    $parent = $this->getEntity();
    $target_type_id = $this->getSetting('target_type');

    // Query for children referencing the parent.
    $query = $this->entityTypeManager()
      ->getStorage($target_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition($parent->getEntityTypeId(), $parent->id());

    // Sort by weight if the field is available.
    $target_type = $this->entityTypeManager()
      ->getDefinition($target_type_id);
    if ($target_type->hasKey('weight')) {
      $query->sort($target_type->getKey('weight'));
    }

    // Attach the query result to the list.
    $this->setValue(
      array_map(static fn ($id) => ['target_id' => $id], $query->execute())
    );
  }

}
