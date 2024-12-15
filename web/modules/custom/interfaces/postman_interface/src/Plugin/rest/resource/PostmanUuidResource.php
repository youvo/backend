<?php

namespace Drupal\postman_interface\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Postman Variables Resource.
 *
 * @RestResource(
 *   id = "postman:uuid",
 *   label = @Translation("Postman Uuid Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/postman/uuid"
 *   }
 * )
 */
class PostmanUuidResource extends ResourceBase {

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponseInterface {

    // Get the uuid of the current user.
    $current_user = $this->currentUser;
    if ($current_user instanceof AccountProxyInterface) {
      $current_user = $current_user->getAccount();
    }
    /** @var \Drupal\user\UserInterface $current_user */
    $current_user_uuid = $current_user->uuid();

    // Compile response with structured data.
    $response = new ResourceResponse([
      'uuid' => $current_user_uuid,
    ]);

    // Add cacheable dependency on the current user.
    $response->getCacheableMetadata()->addCacheContexts(['user']);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->pluginId);

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
