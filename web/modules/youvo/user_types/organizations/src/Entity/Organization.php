<?php

namespace Drupal\organizations\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\user_bundle\Entity\TypedUser;

class Organization extends TypedUser {


  public function hasManager() {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $manager */
    $managers = $this->get('field_manager');
    return !empty($manager->referencedEntities());
  }

  public function getManager() {
    return $this->get('field_manager')->getEntity();
  }

  public function isManager(AccountInterface $account) {
    return $this->get('field_manager')->getEntity()->id() == $account->id();
  }
}
