<?php

namespace Drupal\organizations\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\user_bundle\Entity\TypedUser;
use Drupal\user_types\Utility\Profiler;

class Organization extends TypedUser {

  public function hasManager() {
    return !empty($this->getManager());
  }

  public function getManager() {
    return $this->get('field_manager')->getEntity();
  }

  public function isManager(AccountInterface|int $account) {
    return $this->getManager()->id() == Profiler::id($account);
  }

  public function isOwnerOrManager(AccountInterface|int $account) {
    return $this->id() == Profiler::id($account) || $this->isManager($account);
  }

}
