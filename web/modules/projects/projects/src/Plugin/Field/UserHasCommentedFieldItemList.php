<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * UserHasCommentedFieldItemList class to generate a computed field.
 *
 * @todo Use proper DI after
 *   https://www.drupal.org/project/drupal/issues/2914419 or
 *   https://www.drupal.org/project/drupal/issues/2053415
 */
class UserHasCommentedFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    if (empty($this->list)) {

      // Get project result and user.
      /** @var \Drupal\projects\ProjectResultInterface $project_result */
      $project_result = $this->getEntity();
      $account = \Drupal::currentUser();

      // Set has commented status.
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, !empty($project_result->getCommentByUser($account)));
      $item->getValueProperty()->mergeCacheMaxAge(0);
      $this->list[] = $item;
    }
  }

}
