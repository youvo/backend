<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\organizations\ManagerInterface;

/**
 * AppliedFieldItemList class to generate a computed field.
 *
 * @todo Use DI after https://www.drupal.org/project/drupal/issues/3294266
 */
class UserIsManagerFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue(): void {

    if (empty($this->list)) {

      // Get project and user.
      /** @var \Drupal\projects\ProjectInterface $project */
      $project = $this->getEntity();
      $account = \Drupal::currentUser();

      // Set manager status.
      $owner = $project->getOwner();
      $value = $owner instanceof ManagerInterface ?
        $project->getOwner()->isManager($account) : FALSE;
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $value);
      $item->getValueProperty()->mergeCacheMaxAge(0);
      $this->list[] = $item;
    }
  }

}
