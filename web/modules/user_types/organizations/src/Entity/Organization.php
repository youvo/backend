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
    $uid = $account instanceof AccountInterface ?
      $account->id() : $account;
    return $this->getManager()->id() == $uid;
  }
}
