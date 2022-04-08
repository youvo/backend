<?php

namespace Drupal\organizations\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\Entity\User;
use Drupal\youvo\Utility\RestPrefix;
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
   * @param \Drupal\Core\Session\AccountInterface $organization
   *   The referenced organization.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   */
  public function get(AccountInterface $organization) {

    // Does the organization have a manager?
    $count = 0;
    $current = FALSE;
    /** @var \Drupal\user\Entity\User $organization */
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $managers */
    $managers = $organization->get('field_manager');
    /** @var \Drupal\user\Entity\User $manager */
    foreach ($managers->referencedEntities() as $manager) {
      if ($this->currentUser->id() == $manager->id()) {
        $current = TRUE;
      }
      if (in_array('manager', $manager->getRoles())) {
        $count = $count + 1;
      }
    }
    if ($current) {
      return new ModifiedResourceResponse('Organization already managed by current creative.', 200);
    }
    if ($count) {
      return new ModifiedResourceResponse('Organization already has a manager. Creative may still add themself as a manager.', 409);
    }
    // Otherwise, project is open to apply for creative.
    else {
      return new ModifiedResourceResponse('Creative can manage this organization.', 200);
    }
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\user\Entity\User $organization
   *   The referenced project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(User $organization) {
    $current = FALSE;
    /** @var \Drupal\user\Entity\User $organization */
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $managers */
    $managers = $organization->get('field_manager');
    /** @var \Drupal\user\Entity\User $manager */
    foreach ($managers->referencedEntities() as $manager) {
      if ($this->currentUser->id() == $manager->id()) {
        $current = TRUE;
      }
    }
    if (!$current) {
      $organization->get('field_manager')->appendItem([
        'target_id' => $this->currentUser->id(),
      ]);
      $organization->save();
      return new ModifiedResourceResponse(NULL, 201);
    }
    else {
      return new ModifiedResourceResponse('Organization already managed by current creative.', 200);
    }
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\user\Entity\User $organization
   *   The referenced project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(User $organization) {
    $new_managers = [];
    $current = FALSE;
    /** @var \Drupal\user\Entity\User $organization */
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $managers */
    $managers = $organization->get('field_manager');
    /** @var \Drupal\user\Entity\User $manager */
    foreach ($managers->referencedEntities() as $manager) {
      if ($this->currentUser->id() != $manager->id()) {
        $new_managers[] = ['target_id' => $manager->id()];
      }
      else {
        $current = TRUE;
      }
    }

    // Update managers without current creative.
    if ($current) {
      $organization->set('field_manager', $new_managers);
      $organization->save();
      return new ModifiedResourceResponse(NULL, 201);
    }
    // The creative does not manage this organization.
    else {
      return new ModifiedResourceResponse(NULL, 200);
    }
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
      $route->setPath(RestPrefix::prependPrefix($canonical_path));
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
