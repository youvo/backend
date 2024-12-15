<?php

namespace Drupal\consumer_permissions\Controller;

use Drupal\consumer_permissions\ConsumerPermissions;
use Drupal\consumers\ConsumerStorage;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller that provides access methods for consumer permissions.
 */
class ConsumerPermissionsAccess implements ContainerInjectionInterface {

  /**
   * Constructs a ConsumerAccess object.
   */
  public function __construct(
    protected ConsumerStorage $consumerStorage,
    protected Request $request,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('consumer'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Checks access for users requesting to authenticate with client.
   */
  public function accessClient(AccountProxyInterface $account) {

    // If the client can not be loaded or the user does not have the
    // permission to authenticate with the client access is forbidden.
    if ($account->isAuthenticated()) {
      $client_uuid = $this->request->get('client_id');
      $clients = $this->consumerStorage
        ->loadByProperties(['client_id' => $client_uuid]);
      $client = reset($clients);

      if (empty($client)) {
        return AccessResult::forbidden();
      }

      /** @var \Drupal\consumers\Entity\Consumer $client */
      $permission = ConsumerPermissions::PREFIX . $client->id();
      if (!$account->getAccount()->hasPermission($permission)) {
        return AccessResult::forbidden();
      }
    }

    // The anonymous user has to log in first, before the permissions can be
    // checked. See the ConsumerAuthDecorator. If the checks above do not fail
    // then the user has the permission to authenticate with the client.
    return AccessResult::allowed();
  }

}
