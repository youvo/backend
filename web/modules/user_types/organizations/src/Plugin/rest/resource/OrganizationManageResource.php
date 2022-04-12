<?php

namespace Drupal\organizations\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Project Manage Resource.
 *
 * @RestResource(
 *   id = "organization:manage",
 *   label = @Translation("Organization Manage Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/organizations/{organization}/manage"
 *   }
 * )
 */
class OrganizationManageResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $current_user) {
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
   * Responds GET requests.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The referenced organization.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   */
  public function get(Organization $organization) {

    if ($organization->isManagedBy($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already manages this organization.', 200);
    }

    if ($organization->hasManager()) {
      return new ModifiedResourceResponse('Organization already has a manager.', 409);
    }

    return new ModifiedResourceResponse('Creative can manage this organization.', 200);
  }

  /**
   * Responds PATCH requests.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The referenced organization.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function patch(Organization $organization) {

    if ($organization->isManagedBy($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already manages this organization.', 200);
    }

    if ($organization->hasManager()) {
      return new ModifiedResourceResponse('Organization already has a manager.', 409);
    }

    $organization->setManager($this->currentUser);
    $organization->save();

    return new ModifiedResourceResponse();
  }

  /**
   * Responds DELETE requests.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The referenced organization.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(Organization $organization) {

    if ($organization->isManagedBy($this->currentUser)) {
      $organization->deleteManager();
      $organization->save();
      return new ModifiedResourceResponse();
    }

    if ($organization->hasManager()) {
      return new ModifiedResourceResponse('Another creative manages this organization.', 409);
    }

    return new ModifiedResourceResponse();
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
      $route->setRequirement('_custom_access', '\Drupal\organizations\Controller\OrganizationAccessController::accessManage');
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'organization' => [
          'type' => 'entity:user',
          'converter' => 'paramconverter.uuid',
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
