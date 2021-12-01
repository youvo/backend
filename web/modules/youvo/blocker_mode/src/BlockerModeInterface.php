<?php

namespace Drupal\blocker_mode;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the interface for the blocker mode service.
 */
interface BlockerModeInterface {

  /**
   * Returns whether the site is in blocker mode.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The logged-in user.
   *
   * @return bool
   *   TRUE if the site is in blocker mode.
   */
  public function applies(Request $request, AccountInterface $account);

  /**
   * Determines whether a user has access to the site in blocker mode.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The logged-in user.
   *
   * @return bool
   *   TRUE if the user should be exempted from blocker mode.
   */
  public function exempt(RouteMatchInterface $route_match, AccountInterface $account);

}
