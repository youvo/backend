<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * AppliedFieldItemList class to generate a computed field.
 *
 * @todo Use DI after https://www.drupal.org/project/drupal/issues/3294266
 */
class UserIsParticipantFieldItemList extends FieldItemList {

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

      // Set participant status.
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $project->isParticipant($account));
      $item->getValueProperty()->mergeCacheMaxAge(0);
      $this->list[] = $item;
    }
  }

}
