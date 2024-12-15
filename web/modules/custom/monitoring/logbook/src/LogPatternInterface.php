<?php

namespace Drupal\logbook;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a log pattern entity type.
 */
interface LogPatternInterface extends ConfigEntityInterface {

  /**
   * Gets the text.
   */
  public function getText(): string;

  /**
   * Gets the public text.
   */
  public function getPublicText(bool $fallback = FALSE): string;

  /**
   * Gets the tokens.
   *
   * @param bool $as_array
   *   Whether the tokens should be returned as an array.
   *
   * @return array
   *   The tokens.
   */
  public function getTokens(bool $as_array = FALSE): array;

  /**
   * Gets the standard background color.
   */
  public function getColor(): string;

  /**
   * Returns whether the log pattern is enabled.
   */
  public function isEnabled(): bool;

  /**
   * Returns whether the log pattern is detectable.
   */
  public function isDetectable(): bool;

  /**
   * Returns whether the log pattern is observable.
   */
  public function isObservable(): bool;

  /**
   * Returns whether the log pattern is public.
   */
  public function isPublic(): bool;

  /**
   * Returns whether the log pattern is promoted.
   */
  public function isPromoted(): bool;

  /**
   * Returns whether the log pattern is hidden.
   */
  public function isHidden(): bool;

  /**
   * Gets the associated log text entity.
   */
  public function getLogTextEntity(): ?LogTextInterface;

}
