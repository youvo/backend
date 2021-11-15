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
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      // Get uuid of current unlocked lecture.
      $lecture = $this->progressManager()->currentLecture($this->getEntity());
      $uuid = isset($lecture) ? $lecture->uuid() : '';
      $item = $this->createItem(0, $uuid);

      // Set cache max age zero.
      $item->get('value')->mergeCacheMaxAge(0);
      $this->list[0] = $item;
    }
  }

}
