<?php

namespace Drupal\user_types\Plugin\TopBarItem;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\navigation\Plugin\TopBarItem\PageContext;
use Drupal\user_types\Utility\Profile;

/**
 * Shows the name of a creative or an organization in the top bar.
 *
 * The page context of the navigation module would show the account's email.
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
