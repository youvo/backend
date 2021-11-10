<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * CompletedFieldItemList class to generate a computed field.
 */
class CompletedFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * Mock progress manager dependency injection.
   *
   * @todo Replace with proper DI after
   *    https://www.drupal.org/project/drupal/issues/2914419 or
   *    https://www.drupal.org/project/drupal/issues/2053415
   */
  protected function progressManager() {
    return \Drupal::service('progress.manager');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      // Set completed status.
      /** @var \Drupal\progress\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $this->progressManager()->getCompletedStatus($this->getEntity()));

      // Set cache max age zero.
      $cacheability = (new CacheableMetadata())->setCacheMaxAge(0);
      $item->get('value')->addCacheableDependency($cacheability);
      $this->list[0] = $item;
    }
  }

}
