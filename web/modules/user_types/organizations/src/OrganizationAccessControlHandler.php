<?php

namespace Drupal\organizations;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\user\UserAccessControlHandler;

/**
 * Access controller for the Organization entity.
 */
class OrganizationAccessControlHandler extends UserAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Only organizations should be handled by this handler.
    if (!$entity instanceof Organization) {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Prevent deletion when entity is new.
    if ($operation == 'delete' && $entity->isNew()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // Handle access check downstream for administrators.
    if (in_array('administrator', $account->getRoles())) {
      return parent::checkAccess($entity, $operation, $account);
    }

    // @todo Access check for viewing prospect organizations.

    // @todo Access check for viewing archival organizations.

    /** Explicitly allow managers of organizations to edit the account of the
     * organization. Only allowed for JSON:API requests. This ability will be
     * narrowed down to certain fields in the field access handler.
     * @see \Drupal\organizations\OrganizationFieldAccess
     *
     * @todo Find out how to DI in entity access control handler.
     * @todo Requires testing.
     *
     * @todo This should route match should be deleted and covered by a proper
     *   scope for managers (see implications for basic auth requests in dev).
     */
    $route_defaults = \Drupal::routeMatch()->getRouteObject()->getDefaults();
    if ($operation == 'edit' &&
      $entity->isManager($account) &&
      class_exists('Drupal\\jsonapi\\Routing\\Routes') &&
      \Drupal\jsonapi\Routing\Routes::isJsonApiRequest($route_defaults)) {
      return AccessResult::allowed()->cachePerUser();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   *
   * Only administrators should use the organization creation via admin form.
   * The creation of an organization is implemented in the following.
   * @see \Drupal\organizations\Plugin\rest\resource\OrganizationCreateResource
   *
   * @todo Maybe we can cover this case by permissions.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if (in_array('administrator', $account->getRoles())) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden();
  }

}
