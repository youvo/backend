<?php

namespace Drupal\mailer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a transactional email entity type.
 */
interface TransactionalEmailInterface extends ConfigEntityInterface {

  /**
   * Gets the subject.
   */
  public function subject(): string;

  /**
   * Gets the body.
   */
  public function body(): string;

  /**
   * Gets the tokens.
   *
   * @param bool $as_array
   *   Whether the tokens should be returned as an array.
   *
   * @return array
   *   The tokens.
   */
  public function tokens(bool $as_array = FALSE): array;

}
