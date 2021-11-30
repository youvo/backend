<?php

namespace Drupal\oauth_grant_remote\Controller;

use Drupal\simple_oauth\Controller\Oauth2AuthorizeController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extend Oauth2AuthorizeController to authenticate users remotely.
 */
class Oauth2AuthorizeRemoteController extends Oauth2AuthorizeController {

  /**
   * Authorizes the code generation or prints the confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function authorize(Request $request) {

    // @todo Authenticate user remotely.
    $test = 1;

    return parent::authorize($request);
  }

}
