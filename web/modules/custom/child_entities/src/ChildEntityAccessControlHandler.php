<?php

namespace Drupal\child_entities;

use Drupal\child_entities\Event\ChildEntityAccessEvent;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected LoggerInterface $logger,
    protected ChildEntityHierarchy $hierarchy,
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
      $container->get('logger.factory')->get('child_entities'),
      $container->get('child_entities.hierarchy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {

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
        ->access($entity, $operation, $account, TRUE);
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
    // encounter a child of a child entity, so resolve the top of the chain.
    try {
      $origin_type_id = $this->hierarchy->getOriginEntityType($this->entityType)->id();
      return $this->entityTypeManager
        ->getAccessControlHandler($origin_type_id)
        ->createAccess($entity_bundle, $account, $context, TRUE);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Unable to resolve origin access handler. %type: @message in %function (line %line of %file).', $variables);
      return AccessResult::neutral();
    }
  }

}
