<?php

namespace Drupal\user_types\Plugin\TopBarItem;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\navigation\Plugin\TopBarItem\PageContext;
use Drupal\user_types\Utility\Profile;

/**
 * Shows the name of a creative or an organization in the top bar.
 *
 * Replaces the page context of the navigation module, which shows the email
 * of an account, see user_types_top_bar_item_alter().
 */
class UserTypePageContext extends PageContext {

  /**
   * {@inheritdoc}
   */
  protected function getEntityLabel(EntityInterface $entity): ?string {
    if ($entity instanceof AccountInterface && ($name = Profile::name($entity)) !== '') {
      return $name;
    }
    return parent::getEntityLabel($entity);
  }

}
