<?php

namespace Drupal\blocker_mode;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the default implementation of the maintenance mode service.
 */
class BlockerMode implements BlockerModeInterface {

  /**
   * Constructs a new blocker mode service.
   */
  public function __construct(protected StateInterface $state) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): bool {

    // Get configuration state.
    if (!$this->state->get('system.blocker_mode')) {
      return FALSE;
    }

    // We use the User-Agent header to identify requests from an external
    // client. Although, this is not completely failsafe, because the header can
    // easily be modified, we do not care, because a user without the
    // 'access site' permission is not allowed to do anything more than what is
    // allowed through the client.
    if ($request->headers->has('user-agent')) {
      $user_agent = $request->headers->get('user-agent');
      if (
        $user_agent === 'youvo-frontend' ||
        $user_agent === 'youvo-ip' ||
        $user_agent === 'youvo-subrequests' ||
        str_starts_with($user_agent, 'Postman')
      ) {
        return FALSE;
      }
    }

    // At the moment all request are send to blocker page.
    // Except authorize, authentication, login and logout routes.
    $route_match = RouteMatch::createFromRequest($request);
    if ($route_match->getRouteObject()) {
      $route_name = $route_match->getRouteName();
      $allowed_routes[] = 'oauth2_token.authorize';
      $allowed_routes[] = 'oauth2_token.token';
      $allowed_routes[] = 'simple_oauth.userinfo';
      $allowed_routes[] = 'user.login';
      $allowed_routes[] = 'user.logout';
      // The mail door verifies its own signature.
      $allowed_routes[] = 'mailer_door.go';
      if (in_array($route_name, $allowed_routes, TRUE)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exempt(RouteMatchInterface $route_match, AccountInterface $account): bool {

    // Administrators are welcome.
    if ($account->hasPermission('bypass blocker mode')) {
      return TRUE;
    }

    // Staff are welcome. The route access decides which pages they reach.
    return $account->hasPermission('access site');
  }

}
