<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\progress\ProgressManagerInjectionTrait;

/**
 * UnlockedFieldItemList class to generate a computed field.
 */
class UnlockedFieldItemList extends FieldItemList {

  use ComputedItemListTrait;
  use ProgressManagerInjectionTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue(): void {

    if (!isset($this->list[0])) {

      // Set unlocked status.
      /** @var \Drupal\courses\Entity\Course|\Drupal\lectures\Entity\Lecture $entity */
      $entity = $this->getEntity();
      $is_unlocked = $this->progressManager()->isUnlocked($entity);
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $is_unlocked);
      $item->getValueProperty()->mergeCacheMaxAge(0);
      $this->list[0] = $item;
    }
  }

}
