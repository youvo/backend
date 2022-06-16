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
  public function getText();

  /**
   * Gets the public text.
   *
   * @return string
   *   The public text.
   */
  public function getPublicText(bool $fallback = FALSE);

  /**
   * Gets the tokens.
   *
   * @param bool $as_array
   *   Whether the tokens should be returned as an array.
   *
   * @return array
   *   The tokens.
   */
  public function getTokens(bool $as_array = FALSE);

  /**
   * Gets the standard background color.
   *
   * @return string
   *   The standard background color.
   */
  public function getColor();

  /**
   * Returns whether the log pattern is enabled.
   *
   * @returns bool
   *   The enabled status.
   */
  public function isEnabled();

  /**
   * Returns whether the log pattern is detectable.
   *
   * @returns bool
   *   The detectable status.
   */
  public function isDetectable();

  /**
   * Returns whether the log pattern is observable.
   *
   * @returns bool
   *   The observable status.
   */
  public function isObservable();

  /**
   * Returns whether the log pattern is public.
   *
   * @returns bool
   *   The public status.
   */
  public function isPublic();

  /**
   * Returns whether the log pattern is promoted.
   *
   * @returns bool
   *   The promoted status.
   */
  public function isPromoted();

  /**
   * Returns whether the log pattern is hidden.
   *
   * @returns bool
   *   The hidden status.
   */
  public function isHidden();

  /**
   * Gets the associated log text entity.
   *
   * @returns \Drupal\logbook\LogTextInterface
   *   The log text entity.
   */
  public function getLogTextEntity(): ?LogTextInterface;

}
