<?php

namespace Drupal\organizations\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\ManagerInterface;
use Drupal\user_bundle\Entity\TypedUser;
use Drupal\user_types\Utility\Profile;

class Organization extends TypedUser implements ManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function hasManager() {
    return !$this->get('field_manager')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getManager() {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $manager_field */
    $manager_field = $this->get('field_manager');
    return $manager_field->referencedEntities()[0] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setManager(AccountInterface $account) {
    if ($this->hasManager()) {
      return FALSE;
    }
    $this->get('field_manager')->appendItem($account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteManager() {
    $this->set('field_manager', NULL);
    //$this->get('field_manager')->removeItem(0);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isManager(AccountInterface|int $account) {
    return $this->hasManager() &&
      $this->getManager()->id() == Profile::id($account);
  }

  /**
   * {@inheritdoc}
   */
  public function isOwnerOrManager(AccountInterface|int $account) {
    return $this->id() == Profile::id($account) ||
      $this->isManager($account);
  }

}
