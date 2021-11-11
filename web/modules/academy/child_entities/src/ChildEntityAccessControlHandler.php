<?php

namespace Drupal\child_entities;

use Drupal\child_entities\Context\ChildEntityRouteContextTrait;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;

/**
 * Access controller for child entities.
 *
 * @see \Drupal\child_entities\ChildEntityTrait.
 */
class ChildEntityAccessControlHandler extends EntityAccessControlHandler {

  use ChildEntityRouteContextTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Check if the passed entity is indeed a child entity.
    /** @var \Drupal\child_entities\ChildEntityInterface $entity */
    if (!($entity instanceof ChildEntityInterface)) {
      throw new AccessException('The ChildEntityAccessControlHandler was called by an entity that does not implement the ChildEntityInterface.');
    }

    // Check the admin_permission as defined in child entity annotation.
    $admin_permission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($admin_permission)) {
      return AccessResult::allowed()->cachePerUser();
    }

    // First check if user has permission to access courses.
    try {
      $origin = $entity->getOriginEntity();
      $access = \Drupal::entityTypeManager()
        ->getAccessControlHandler($origin->getEntityTypeId())
        ->checkAccess($entity, $operation, $account);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('academy')
        ->error('Unable to resolve origin access handler. %type: @message in %function (line %line of %file).', $variables);
      return AccessResult::neutral();
    }

    // Let other modules hook into access decision.
    // @see progress.module
    $this->moduleHandler()
      ->invokeAll('child_entities_check_access', [&$access, $origin, $account]);

    // If all conditions are met allow access.
    if ($access->isAllowed()) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Otherwise, deny access - but do not cache access result for user. Because
    // a user might access a child before the origin progress is created. Then,
    // cached access will deliver wrong results.
    return AccessResult::forbidden()->cachePerUser()->setCacheMaxAge(0);
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist. It
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {

    // Check the admin_permission as defined in child entity annotation.
    $admin_permission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($admin_permission)) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Rely on create access of origin.
    try {
      /** @var \Drupal\child_entities\ChildEntityInterface $entity */
      $parent_entity_type = $this->entityType->getKey('parent');
      $parent = $this->getParentEntityFromRoute($parent_entity_type);
      $origin = $parent instanceof ChildEntityInterface ?
        $parent->getOriginEntity() : $parent;
      return \Drupal::entityTypeManager()
        ->getAccessControlHandler($origin->getEntityTypeId())
        ->checkCreateAccess($account, $context, $account);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('academy')
        ->error('Unable to resolve origin access handler. %type: @message in %function (line %line of %file).', $variables);
      return AccessResult::neutral();
    }
  }

}
