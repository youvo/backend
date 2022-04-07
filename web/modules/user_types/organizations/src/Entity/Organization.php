<?php

namespace Drupal\organizations\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\user_bundle\Entity\TypedUser;

class Organization extends TypedUser {

  public function hasManager() {
    return !empty($this->getManager());
  }

  public function getManager() {
    return $this->get('field_manager')->getEntity();
  }

  public function isManager(AccountInterface|int $account) {
    return $this->getManager()->id() == $this->getUid($account);
  }

  public function isOwnerOrManager(AccountInterface|int $account) {
    return $this->id() == $this->getUid($account) || $this->isManager($account);
  }

  /**
   * Helper to get uid of an account.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account or the uid.
   * @return \Drupal\Core\Session\AccountInterface|int
   *   The uid.
   */
  private function getUid(AccountInterface|int $account) {
    return $account instanceof AccountInterface ? $account->id() : $account;
  }
}
