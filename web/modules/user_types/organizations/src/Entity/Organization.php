<?php

namespace Drupal\organizations\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\ManagerInterface;
use Drupal\user_bundle\Entity\TypedUser;
use Drupal\user_types\Utility\Profile;

/**
 * Provides methods for the organization entity.
 */
class Organization extends TypedUser implements ManagerInterface {

  const ROLE_PROSPECT = 'prospect';
  const ROLE_ORGANIZATION = 'organization';
  const ROLE_ARCHIVAL = 'archival';

  /**
   * Gets the contact.
   */
  public function getContact(): string {
    return $this->get('field_contact')->value;
  }

  /**
   * Gets the name.
   */
  public function getName(): string {
    return $this->get('field_name')->value;
  }

  /**
   * Gets the phone number.
   */
  public function getPhoneNumber(): string {
    return $this->get('field_phone')->value;
  }

  /**
   * Gets the address.
   */
  public function getAddress(): string {
    $country = !empty($this->get('field_country')->value) ?
      ', ' . !empty($this->get('field_country')->value) : '';
    return $this->get('field_street')->value . ', ' .
      $this->get('field_zip')->value . ' ' . $this->get('field_city')->value .
      $country;
  }

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
    $manager_references = $manager_field->referencedEntities();
    /** @var \Drupal\user\UserInterface|null $manager */
    $manager = $manager_references[0] ?? NULL;
    return !empty($manager) ? $manager : NULL;
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

  /**
   * Determines if this organization has the role archival.
   */
  public function hasRoleArchival() {
    return in_array(self::ROLE_ARCHIVAL, $this->getRoles());
  }

  /**
   * Determines if this organization has the role prospect.
   */
  public function hasRoleProspect() {
    return in_array(self::ROLE_PROSPECT, $this->getRoles());
  }

  /**
   * Determines if this organization has the role organization.
   */
  public function hasRoleOrganization() {
    return in_array(self::ROLE_ORGANIZATION, $this->getRoles());
  }

  /**
   * Promotes a prospect to a proper organization.
   *
   * @return $this|false
   *   The current organization or FALSE if organization is not a prospect.
   */
  public function promoteProspect() {
    if (!in_array('prospect', $this->getRoles())) {
      return FALSE;
    }
    $this->removeRole('prospect');
    $this->addRole('organization');
    return $this;
  }

}
