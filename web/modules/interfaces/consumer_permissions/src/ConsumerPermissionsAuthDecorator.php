<?php

namespace Drupal\consumer_permissions;

use Drupal\Component\Utility\UrlHelper;
use Drupal\consumers\ConsumerStorage;
use Drupal\Core\Http\RequestStack;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\HttpFoundation\Request;

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
class ConsumerPermissionsAuthDecorator implements UserAuthInterface {

  /**
   * The consumer storage.
   *
   * @var \Drupal\consumers\ConsumerStorage
   */
  protected ConsumerStorage $consumerStorage;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected ?Request $request;

  /**
   * The user auth service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected UserAuthInterface $userAuth;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * Constructs a ConsumerPermissionsAuthDecorator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Http\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user auth service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    UserAuthInterface $user_auth
  ) {
    /** @var \Drupal\consumers\ConsumerStorage $consumer_storage */
    $consumer_storage = $entity_type_manager->getStorage('consumer');
    $this->consumerStorage = $consumer_storage;
    $this->request = $request_stack->getCurrentRequest();
    $this->userAuth = $user_auth;
    /** @var \Drupal\user\UserStorageInterface $user_storage */
    $user_storage = $entity_type_manager->getStorage('user');
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate($username, $password) {

    // Resolve client ID and response type from destination.
    if ($this->request->query->has('destination')) {
      $destination = $this->request->query->get('destination');
      $options = UrlHelper::parse($destination);
      $client_id = $options['query']['client_id'] ?? NULL;
    }

    // Checking permissions is unwanted if this is not a OAuth request.
    if (!isset($client_id)) {
      return $this->userAuth->authenticate($username, $password);
    }

    $clients = $this->consumerStorage
      ->loadByProperties(['uuid' => $client_id]);
    $client = reset($clients);

    // Should not happen since the client is validated in the controller.
    if (empty($client)) {
      return FALSE;
    }

    // Using the same condition for the arguments as in the decorated service.
    if (!empty($username) && strlen($password) > 0) {

      $accounts = $this->userStorage->loadByProperties(['name' => $username]);
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

}
