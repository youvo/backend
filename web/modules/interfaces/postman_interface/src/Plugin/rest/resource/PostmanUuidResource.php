<?php

namespace Drupal\postman_interface\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
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
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a QuestionSubmissionResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function get() {

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
  public function routes() {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = strtr($this->pluginId, ':', '.');

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
