<?php

namespace Drupal\organizations;

use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;

/**
 * Interface that provides methods for a managed user entity.
 */
interface ManagerInterface {

  /**
   * Checks whether the organization has a manager.
   */
  public function hasManager(): bool;

  /**
   * Gets the manager.
   */
  public function getManager(): ?Creative;

  /**
   * Sets the manager.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return $this|false
   *   The current organization or FALSE if the organization has a manager.
   */
  public function setManager(AccountInterface $account): static|false;

  /**
   * Deletes the manager.
   */
  public function deleteManager(): static;

  /**
   * Determines whether the account is the manager.
   */
  public function isManager(AccountInterface|int $account): bool;

  /**
   * Determines whether the account is the owner (organization) or the manager.
   */
  public function isOwnerOrManager(AccountInterface|int $account): bool;

}
