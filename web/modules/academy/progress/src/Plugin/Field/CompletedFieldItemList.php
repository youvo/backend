<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\progress\ProgressManagerInjectionTrait;

/**
 * CompletedFieldItemList class to generate a computed field.
 */
class CompletedFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;
  use ProgressManagerInjectionTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      // Set completed status.
      /** @var \Drupal\progress\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $this->progressManager()->isCompleted($this->getEntity()));

      // Set cache max age zero.
      $item->get('value')->mergeCacheMaxAge(0);
      $this->list[0] = $item;
    }
  }

}
