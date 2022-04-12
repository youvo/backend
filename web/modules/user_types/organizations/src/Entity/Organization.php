<?php

namespace Drupal\organizations\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\user_bundle\Entity\TypedUser;
use Drupal\user_types\Utility\Profiler;

class Organization extends TypedUser {

  public function hasManager() {
    return !$this->get('field_manager')->isEmpty();
  }

  public function getManager() {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $manager_field */
    $manager_field = $this->get('field_manager');
    return $manager_field->referencedEntities()[0] ?? NULL;
  }

  public function setManager(AccountInterface $account) {
    if ($this->hasManager()) {
      return FALSE;
    }
    $this->get('field_manager')->appendItem($account->id());
    return $this;
  }

  public function deleteManager() {
    $this->get('field_manager')->removeItem(0);
    return $this;
  }

  public function isManagedBy(AccountInterface|int $account) {
    return $this->hasManager() &&
      $this->getManager()->id() == Profiler::id($account);
  }

  public function isOwnedOrManagedBy(AccountInterface|int $account) {
    return $this->id() == Profiler::id($account) || $this->isManagedBy($account);
  }

}
