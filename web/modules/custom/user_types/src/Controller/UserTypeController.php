<?php

namespace Drupal\user_types\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\user_types\Utility\Profile;

/**
 * Controller routines for the pages of user types.
 */
class UserTypeController extends ControllerBase {

  /**
   * Gets the title of a user page.
   *
   * Creatives and organizations use their name, because their account name is
   * an email.
   *
   * @return array|string
   *   The title.
   */
  public function userTitle(?UserInterface $user = NULL): array|string {
    if (!$user) {
      return '';
    }
    $name = Profile::name($user);
    if ($name !== '') {
      return $name;
    }
    return ['#markup' => $user->getDisplayName(), '#allowed_tags' => Xss::getHtmlTagList()];
  }

}
