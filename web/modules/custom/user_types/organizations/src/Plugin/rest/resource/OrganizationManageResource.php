<?php

namespace Drupal\organizations\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\organizations\Event\OrganizationDisbandEvent;
use Drupal\organizations\Event\OrganizationManageEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected AccountInterface $currentUser,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The referenced organization.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function get(Organization $organization) {

    if ($organization->isManager($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already manages this organization.', 200);
    }

    if ($organization->hasManager()) {
      return new ModifiedResourceResponse('Organization already has a manager.', 409);
    }

    return new ModifiedResourceResponse('Creative can manage this organization.', 200);
  }

  /**
   * Responds to PATCH requests.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The referenced organization.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(Organization $organization) {

    if ($organization->isManager($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already manages this organization.', 200);
    }

    if ($organization->hasManager()) {
      return new ModifiedResourceResponse('Organization already has a manager.', 409);
    }

    $organization->setManager($this->currentUser);
    $organization->save();

    // Dispatch organization manage event.
    $event = new OrganizationManageEvent($organization, $organization->getManager());
    $this->eventDispatcher->dispatch($event);

    return new ModifiedResourceResponse();
  }

  /**
   * Responds to DELETE requests.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The referenced organization.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(Organization $organization) {

    if ($organization->isManager($this->currentUser)) {
      $manager = $organization->getManager();
      $organization->deleteManager();
      $organization->save();
      $event = new OrganizationDisbandEvent($organization, $manager);
      $this->eventDispatcher->dispatch($event);
      return new ModifiedResourceResponse();
    }

    if ($organization->hasManager()) {
      // Passing a message will cause a decoding error in Symfony.
      return new ModifiedResourceResponse(NULL, 409);
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
