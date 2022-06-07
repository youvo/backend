<?php

namespace Drupal\logbook;

use Drupal\child_entities\ChildEntityInterface;

/**
 * Provides an interface defining a log text entity type.
 */
interface LogTextInterface extends ChildEntityInterface {

  /**
   * Gets the text.
   *
   * @return string
   *   The text.
   */
  public function getText();

  /**
   * Gets the public text.
   *
   * @return string
   *   The public text.
   */
  public function getPublicText();

}
