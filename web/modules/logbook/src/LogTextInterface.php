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
   * Sets the text.
   *
   * @param string $text
   *   The text.
   *
   * @return \Drupal\logbook\LogTextInterface
   *   The current log text.
   */
  public function setText(string $text): LogTextInterface;

  /**
   * Gets the public text.
   *
   * @return string
   *   The public text.
   */
  public function getPublicText();

  /**
   * Sets the public text.
   *
   * @param string $public_text
   *   The public text.
   *
   * @return \Drupal\logbook\LogTextInterface
   *   The current log text.
   */
  public function setPublicText(string $public_text): LogTextInterface;

}
