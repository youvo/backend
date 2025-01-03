<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\progress\ProgressManagerInjectionTrait;

/**
 * CompletedFieldItemList class to generate a computed field.
 */
class CompletedFieldItemList extends FieldItemList {

  use ComputedItemListTrait;
  use ProgressManagerInjectionTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue(): void {

    if (!isset($this->list[0])) {

      // Set completed status.
      /** @var \Drupal\courses\Entity\Course|\Drupal\lectures\Entity\Lecture $entity */
      $entity = $this->getEntity();
      $is_completed = $this->progressManager()->isCompleted($entity);
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $is_completed);
      $item->getValueProperty()->mergeCacheMaxAge(0);
      $this->list[0] = $item;
    }
  }

}
