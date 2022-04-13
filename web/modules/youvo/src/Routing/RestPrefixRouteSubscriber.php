<?php

namespace Drupal\youvo\Routing;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber for rest routes to handle path prefix.
 *
 * Introduce this route alteration to obscure the api paths. Follows the
 * security recommendations for JSON:API. The paths for JSON:API resources is
 * set through the JSON:API Extras settings page. Here, we prepend the prefix
 * for all custom REST routes. The value is fetched during installation from
 * the environment variables. In principle one should make sure that both
 * configurations are in sync.
 */
class RestPrefixRouteSubscriber extends RouteSubscriberBase {

  const REQUEST_METHODS = [
    'HEAD',
    'GET',
    'POST',
    'PUT',
    'DELETE',
    'TRACE',
    'OPTIONS',
    'CONNECT',
    'PATCH',
  ];

  /**
   * The resource config storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $resourceConfigStorage;

  /**
   * Constructs a RestPrefixRouteSubscriber object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->resourceConfigStorage = $entity_type_manager->getStorage('rest_resource_config');
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {

    /** @var \Drupal\rest\RestResourceConfigInterface[] $rest_resources */
    $rest_resources = $this->resourceConfigStorage
      ->loadByProperties(['status' => 1]);

    // Exclude postman interface.
    unset($rest_resources['postman.variables']);

    // Prepend prefix to all available resource paths.
    foreach ($rest_resources as $resource) {
      foreach (self::REQUEST_METHODS as $method) {
        if ($route = $collection->get($this->routeName($resource, $method))) {
          $route->setPath($this->prependPrefix($route->getPath()));
        }
      }
    }
  }

  /**
   * Gets the route name for a resource and a method.
   *
   * Note that the plugin ID follows the naming convention {foo}:{bar}. The
   * route name is defined as rest.{foo}.{bar}.{METHOD} in the collection.
   */
  protected function routeName($resource, $method) {
    $resource_id = str_replace(':', '.', $resource->id());
    return 'rest.' . $resource_id . '.' . $method;
  }

  /**
   * Prepends rest prefix to path.
   */
  public static function prependPrefix(string $path) {
    $prefix = \Drupal::config('youvo.settings')->get('rest_prefix');
    return !empty($prefix) ? '/' . $prefix . $path : $path;
  }

}
