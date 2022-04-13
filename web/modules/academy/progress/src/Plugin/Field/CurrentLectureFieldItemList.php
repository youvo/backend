<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\progress\ProgressManagerInjectionTrait;

/**
 * ProgressFieldItemList class to generate a computed field.
 */
class CurrentLectureFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;
  use ProgressManagerInjectionTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      // Get uuid of current unlocked lecture.
      /** @var \Drupal\courses\Entity\Course $course */
      $course = $this->getEntity();
      $lecture = $this->progressManager()->currentLecture($course);
      $uuid = isset($lecture) ? $lecture->uuid() : '';
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem $item */
      $item = $this->createItem(0, $uuid);
      $item->getValueProperty()->mergeCacheMaxAge(0);
      $this->list[0] = $item;
    }
  }

}
