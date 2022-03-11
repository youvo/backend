<?php

namespace Drupal\prospects\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Plugin\rest\resource\OrganizationManageResource;
use Drupal\user\Entity\User;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Prospect Manage Resource.
 *
 * @RestResource(
 *   id = "prospect:manage",
 *   label = @Translation("Prospect Manage Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/prospects/{organization}/manage"
 *   }
 * )
 */
class ProspectManageResource extends OrganizationManageResource {

  /**
   * Responds GET requests.
   *
   * @param \Drupal\Core\Session\AccountInterface $organization
   *   The referenced prospect.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   */
  public function get(AccountInterface $organization) {
    return parent::get($organization);
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\user\Entity\User $organization
   *   The referenced prospect.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(User $organization) {
    return parent::post($organization);
  }

  /**
   * Responds DELETE requests.
   *
   * @param \Drupal\user\Entity\User $organization
   *   The referenced prospect.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(User $organization) {
    return parent::delete($organization);
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
      $route->setRequirement('_custom_access', '\Drupal\prospects\Controller\ProspectAccessController::accessProspectManage');
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
