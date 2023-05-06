<?php

namespace Drupal\organizations;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface that provides methods for a managed user entity.
 */
interface ManagerInterface {

  /**
   * Checks whether the organization has a manager.
   *
   * @return bool
   *   Has manager?
   */
  public function hasManager();

  /**
   * Gets the manager.
   *
   * @return \Drupal\creatives\Entity\Creative|null
   *   The manager.
   */
  public function getManager();

  /**
   * Sets the manager.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return $this|false
   *   The current organization or FALSE if the organization has a manager.
   */
  public function setManager(AccountInterface $account);

  /**
   * Deletes the manager.
   *
   * @return $this
   *   The current organization.
   */
  public function deleteManager();

  /**
   * Determines whether the account is the manager.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account.
   *
   * @return bool
   *   Is manager?
   */
  public function isManager(AccountInterface|int $account);

  /**
   * Determines whether the account is the owner (organization) or the manager.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account.
   *
   * @return bool
   *   Is owner or manager?
   */
  public function isOwnerOrManager(AccountInterface|int $account);

}
