<?php

namespace Drupal\consumer_permissions;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserAuthenticationInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Validates user permissions to authenticate with certain consumers.
 *
 * Some security considerations: This authentication may seem week in regard to
 * skipping the permission check. But, the destination redirects back to the
 * 'oauth.authorize' route which again verifies the client. Therefore, if the
 * query parameters somehow get exploited the user is able to log in to the
 * website (because the user has an account), but will not be able to authorize
 * with the client later.
 */
class ConsumerPermissionsAuthDecorator implements UserAuthInterface, UserAuthenticationInterface {

  /**
   * Constructs a ConsumerPermissionsAuthDecorator object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
    protected UserAuthInterface|UserAuthenticationInterface $userAuth,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function authenticate($username, $password): bool {

    // Resolve client ID and response type from destination.
    $current_request = $this->requestStack->getCurrentRequest();
    if ($current_request instanceof Request && $current_request->query->has('destination')) {
      $destination = $current_request->query->get('destination');
      $options = UrlHelper::parse($destination);
      $client_id = $options['query']['client_id'] ?? NULL;
    }

    // Checking permissions is unwanted if this is not a OAuth request.
    if (!isset($client_id)) {
      return $this->userAuth->authenticate($username, $password);
    }

    $clients = $this->entityTypeManager
      ->getStorage('consumer')
      ->loadByProperties(['client_id' => $client_id]);
    $client = reset($clients);

    // Should not happen since the client is validated in the controller.
    if (empty($client)) {
      return FALSE;
    }

    // Using the same condition for the arguments as in the decorated service.
    if (!empty($username) && $password !== '') {

      $accounts = $this->entityTypeManager
        ->getStorage('user')
        ->loadByProperties(['name' => $username]);
      $account = reset($accounts);

      // Do not authenticate if unable to load account.
      if (empty($account)) {
        return FALSE;
      }

      // Check whether account has permission to authenticate with client.
      /** @var \Drupal\consumers\Entity\Consumer $client */
      $permission = ConsumerPermissions::PREFIX . $client->id();
      /** @var \Drupal\user\UserInterface $account */
      if ($account->hasPermission($permission)) {
        return $this->userAuth->authenticate($username, $password);
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupAccount($identifier): UserInterface|false {
    return $this->userAuth->lookupAccount($identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticateAccount(UserInterface $account, string $password): bool {
    // @todo Clean up implementation when removing the deprecated interface.
    return $this->authenticate($account->getAccountName(), $password);
  }

}
