<?php

namespace Drupal\organizations\Plugin\TopBarItem;

use Drupal\Core\Entity\EntityInterface;
use Drupal\navigation\Plugin\TopBarItem\PageContext;
use Drupal\organizations\Entity\Organization;

/**
 * Shows the name of an organization in the top bar, not its email.
 *
 * Replaces the page context of the navigation module, see
 * organizations_top_bar_item_alter().
 */
class OrganizationPageContext extends PageContext {

  /**
   * {@inheritdoc}
   */
  protected function getEntityLabel(EntityInterface $entity): ?string {
    if ($entity instanceof Organization && $entity->getName() !== '') {
      return $entity->getName();
    }
    return parent::getEntityLabel($entity);
  }

}
