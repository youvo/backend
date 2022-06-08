<?php

namespace Drupal\logbook;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a log pattern entity type.
 */
interface LogPatternInterface extends ConfigEntityInterface {

  /**
   * Gets the text.
   *
   * @return string
   *   The text.
   */
  public function text();

  /**
   * Gets the public text.
   *
   * @return string
   *   The public text.
   */
  public function publicText(bool $fallback = FALSE);

  /**
   * Gets the tokens.
   *
   * @param bool $as_array
   *   Whether the tokens should be returned as an array.
   *
   * @return array
   *   The tokens.
   */
  public function tokens(bool $as_array = FALSE);

  /**
   * Returns whether the log pattern is promoted.
   *
   * @returns bool
   *   The promoted status.
   */
  public function promoted();

  /**
   * Returns whether the log pattern is hidden.
   *
   * @returns bool
   *   The hidden status.
   */
  public function hidden();

  /**
   * Gets the associated log text entity.
   *
   * @returns \Drupal\logbook\LogTextInterface
   *   The log text entity.
   */
  public function getLogTextEntity(): ?LogTextInterface;

}
