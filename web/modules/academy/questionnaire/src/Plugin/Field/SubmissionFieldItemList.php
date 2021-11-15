<?php

namespace Drupal\questionnaire\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * SubmissionFieldItemList class to generate a computed field.
 */
class SubmissionFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      // Set value.
      $item = $this->createItem(0, 'Wurst');

      // Set cache max age zero.
      $item->get('value')->mergeCacheMaxAge(0);
      $this->list[0] = $item;
      $this->list[1] = $item;
    }
  }

}
