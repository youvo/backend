<?php

namespace Drupal\creatives\Entity;

use Drupal\user_bundle\Entity\TypedUser;

/**
 * Provides methods for the creative user entity.
 */
class Creative extends TypedUser {

  /**
   * Gets the name.
   */
  public function getName(): string {
    return $this->get('field_name')->value;
  }

  /**
   * Sets the name.
   */
  public function setName(string $name): Creative {
    $this->set('field_name', $name);
    return $this;
  }

  /**
   * Gets the phone number.
   */
  public function getPhoneNumber(): string {
    return $this->get('field_phone')->value;
  }

  /**
   * Sets the phone number.
   */
  public function setPhoneNumber(string $phone_number): Creative {
    $this->set('field_phone', $phone_number);
    return $this;
  }

}
