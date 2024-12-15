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
   */
  public function applies(Request $request): bool;

  /**
   * Determines whether a user has access to the site in blocker mode.
   */
  public function exempt(RouteMatchInterface $route_match, AccountInterface $account): bool;

}
