<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes referencing children of parent.
 */
class ComputedProjectReferenceFieldItemList extends EntityReferenceFieldItemList {

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

    // Fetch the user and determine respective field.
    $account = $this->getEntity();
    $field = $account->bundle() == 'user' ? 'field_participants' : 'uid';

    // Query projects referencing user.
    $query = $this->entityTypeManager()
      ->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'project')
      ->condition($field, $account->id());

    // Attach the query result to the list.
    $this->setValue(
      array_map(fn ($id) => ['target_id' => $id], $query->execute())
    );
  }

}
