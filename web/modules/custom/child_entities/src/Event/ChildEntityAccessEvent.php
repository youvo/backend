<?php

namespace Drupal\child_entities\Event;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a child entity access event.
 */
class ChildEntityAccessEvent extends Event {

  /**
   * Constructs a ChildEntityAccessEvent object.
   */
  public function __construct(
    protected AccessResultInterface $accessResult,
    protected AccountInterface $account,
    protected ChildEntityInterface $entity,
  ) {}

  /**
   * Gets the access result.
   */
  public function getAccessResult(): AccessResultInterface {
    return $this->accessResult;
  }

  /**
   * Sets the access result.
   */
  public function setAccessResult(AccessResultInterface $access_result): ChildEntityAccessEvent {
    $this->accessResult = $access_result;
    return $this;
  }

  /**
   * Gets the account.
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * Gets the entity.
   */
  public function getEntity(): ChildEntityInterface {
    return $this->entity;
  }

}
