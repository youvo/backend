<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\progress\LectureProgressManager;

/**
 * ComputedCompletedStatusFieldItemList class to generate a computed field.
 */
class LectureCompletedFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    // Get progress manager for lecture.
    $progress_manager = LectureProgressManager::create($this->getEntity());

    // Set completed status.
    /** @var \Drupal\progress\Plugin\Field\FieldType\CacheableBooleanItem $item */
    $item = $this->createItem(0, $progress_manager->getCompletedStatus());

    // Set cache max age zero.
    $cacheability = (new CacheableMetadata())->setCacheMaxAge(0);
    $item->get('value')->addCacheableDependency($cacheability);
    $this->list[0] = $item;
  }

}
