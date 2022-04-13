<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * AppliedFieldItemList class to generate a computed field.
 *
 * @todo Use proper DI after
 *   https://www.drupal.org/project/drupal/issues/2914419 or
 *   https://www.drupal.org/project/drupal/issues/2053415
 */
class UserIsManagerFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    if (empty($this->list)) {

      // Get project and user.
      /** @var \Drupal\projects\ProjectInterface $project */
      $project = $this->getEntity();
      $account = \Drupal::currentUser();
      /** @var \Drupal\organizations\Entity\Organization $organization */
      $organization = $project->getOwner();

      // Set manager status.
      $item = $this->createItem(0, $organization->isManager($account));
      $item->get('value')->mergeCacheMaxAge(0);
      $this->list[] = $item;
    }
  }

}
