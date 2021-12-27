<?php

namespace Drupal\child_entities;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for child entities.
 *
 * @see \Drupal\child_entities\ChildEntityTrait.
 */
class ChildEntityAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * WebformEntityAccessControlHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    parent::__construct($entity_type);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('academy')
    );
  }

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

    // First check if user has permission to access the origin entity.
    try {
      $origin = $entity->getOriginEntity();
      $access = $this->entityTypeManager
        ->getAccessControlHandler($origin->getEntityTypeId())
        ->checkAccess($entity, $operation, $account);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger
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
   * Separate from the checkAccess because the entity does not yet exist.
   *
   * It will be created during the 'add' process.
   *
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {

    // Check the admin_permission as defined in child entity annotation.
    $admin_permission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($admin_permission)) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Get the creation access control handler from the origin entity. We might
    // encounter a child of a child entity. Therefore, loop until the parent
    // entity is not a child entity.
    try {
      do {
        $entity_type = $parent_entity_type ?? $this->entityType;
        $parent_key = $entity_type->getKey('parent');
        $parent_entity_type = $this->entityTypeManager->getDefinition($parent_key);
        $parent_class = $parent_entity_type->getOriginalClass();
      } while (in_array(ChildEntityTrait::class, class_uses($parent_class)));

      return $this->entityTypeManager
        ->getAccessControlHandler($parent_key)
        ->checkCreateAccess($account, $context, $account);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('Unable to resolve origin access handler. %type: @message in %function (line %line of %file).', $variables);
      return AccessResult::neutral();
    }
  }

}
