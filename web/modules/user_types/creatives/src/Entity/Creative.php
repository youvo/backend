<?php

namespace Drupal\creatives\Entity;

use Drupal\user_bundle\Entity\TypedUser;

/**
 * Provides methods for the creative user entity.
 */
class Creative extends TypedUser {

  /**
   * Gets the name.
   *
   * @return string
   *   The name.
   */
  public function getName() {
    return $this->get('field_name')->value;
  }

}
