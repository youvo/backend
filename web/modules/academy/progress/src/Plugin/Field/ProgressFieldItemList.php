<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\progress\ProgressManagerInjectionTrait;

/**
 * ProgressFieldItemList class to generate a computed field.
 */
class ProgressFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;
  use ProgressManagerInjectionTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      // Set progression.
      /** @var \Drupal\courses\Entity\Course $course */
      $course = $this->getEntity();
      $progression = $this->progressManager()->calculateProgression($course);
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $progression);
      $item->getValueProperty()->mergeCacheMaxAge(0);
      $this->list[0] = $item;
    }
  }

}
