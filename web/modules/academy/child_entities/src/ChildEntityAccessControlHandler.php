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
      return AccessResult::allowed();
    }

    // Rely on origin entity access handling.
    try {
      return $entity->getOriginEntity()->access($operation, $account, TRUE);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('child_entities')
        ->error('Unable to resolve origin access handler. %type: @message in %function (line %line of %file).', $variables);
      return AccessResult::neutral();
    }
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
      return AccessResult::allowed();
    }

    // Get parent edit access handler. This is not the cleanest solution, but
    // create permissions should always be connected to edit permissions.
    $parent_entity_type = $this->entityType->getKey('parent');
    return $this->getParentEntityFromRoute($parent_entity_type)->access('edit', $account, TRUE);
  }

}
