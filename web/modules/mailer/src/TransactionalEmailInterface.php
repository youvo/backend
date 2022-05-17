<?php

namespace Drupal\mailer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a transactional email entity type.
 */
interface TransactionalEmailInterface extends ConfigEntityInterface {

  /**
   * Gets the subject.
   *
   * @return string
   *   The subject.
   */
  public function subject();

  /**
   * Gets the body.
   *
   * @return string
   *   The body.
   */
  public function body();

  /**
   * Gets the tokens.
   *
   * @param bool $as_array
   *   Whether the tokens should be returned as an array.
   *
   * @return array
   *   The tokens.
   */
  public function tokens(bool $as_array);

}
