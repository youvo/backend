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
   * @return array
   *   The tokens.
   */
  public function tokens();

}
