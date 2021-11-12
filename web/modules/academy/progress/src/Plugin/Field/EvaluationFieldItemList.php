<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * EvaluationFieldItemList class to generate a computed field.
 */
class EvaluationFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      // Set some value.
      $item = $this->createItem(0, ['frech' => 'Hello World!']);
      $this->list[0] = $item;
    }
  }

}
