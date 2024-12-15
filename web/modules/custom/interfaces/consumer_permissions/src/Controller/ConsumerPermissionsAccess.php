<?php

namespace Drupal\consumer_permissions\Controller;

use Drupal\consumer_permissions\ConsumerPermissions;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller that provides access methods for consumer permissions.
 */
class ConsumerPermissionsAccess implements ContainerInjectionInterface {

  /**
   * Constructs a ConsumerAccess object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Checks access for users requesting to authenticate with client.
   */
  public function accessClient(AccountProxyInterface $account): AccessResultInterface {

    // If the client can not be loaded or the user does not have the
    // permission to authenticate with the client access is forbidden.
    if ($account->isAuthenticated()) {
      $client_uuid = $this->requestStack->getCurrentRequest()->get('client_id');
      $clients = $this->entityTypeManager
        ->getStorage('consumer')
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
