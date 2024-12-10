<?php

namespace Drupal\child_entities;

use Drupal\child_entities\Event\ChildEntityAccessEvent;
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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Access controller for child entities.
 *
 * @see \Drupal\child_entities\ChildEntityTrait.
 */
class ChildEntityAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Constructs a ChildEntityAccessControlHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('logger.factory')->get('child_entities')
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
      /** @var \Drupal\Core\Entity\EntityAccessControlHandler $access_handler */
      $access_handler = $this->entityTypeManager
        ->getAccessControlHandler($origin->getEntityTypeId());
      $access = $access_handler->checkAccess($entity, $operation, $account);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Unable to resolve origin access handler. %type: @message in %function (line %line of %file).', $variables);
      return AccessResult::neutral();
    }

    // Dispatch child entity access event.
    $event = new ChildEntityAccessEvent($access, $account, $entity);
    $this->eventDispatcher->dispatch($event);

    // If all conditions are met allow access.
    if ($event->getAccessResult()->isAllowed()) {
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

      /** @var \Drupal\Core\Entity\EntityAccessControlHandler $access_handler */
      $access_handler = $this->entityTypeManager
        ->getAccessControlHandler($parent_key);
      return $access_handler->checkCreateAccess($account, $context, $entity_bundle);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('Unable to resolve origin access handler. %type: @message in %function (line %line of %file).', $variables);
      return AccessResult::neutral();
    }
  }

}
