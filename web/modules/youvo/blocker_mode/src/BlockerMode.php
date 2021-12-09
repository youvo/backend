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
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new blocker mode service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request, AccountInterface $account) {

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
      if ($user_agent == 'youvo-frontend' ||
        $user_agent == 'youvo-ip' ||
        str_starts_with($user_agent, 'Postman')) {
        return FALSE;
      }
    }

    // At the moment all request are send to blocker page.
    // Except authorize, login and logout routes.
    $route_match = RouteMatch::createFromRequest($request);
    if ($route_match->getRouteObject()) {
      $route_name = $route_match->getRouteName();
      $allowed_routes[] = 'oauth2_token.authorize';
      $allowed_routes[] = 'user.login';
      $allowed_routes[] = 'user.logout';
      if (in_array($route_name, $allowed_routes)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exempt(RouteMatchInterface $route_match, AccountInterface $account) {

    // Administrators are welcome.
    if ($account->hasPermission('administer site')) {
      return TRUE;
    }

    // We disallow all /user paths. Note that blocker mode does not apply for
    // the login and logout route.
    $forbidden_path = FALSE;
    if ($route_match->getRouteObject()) {
      $path = $route_match->getRouteObject()->getPath();
      $forbidden_path = str_starts_with($path, '/user');
    }

    return $account->hasPermission('access site') && !$forbidden_path;
  }

}
