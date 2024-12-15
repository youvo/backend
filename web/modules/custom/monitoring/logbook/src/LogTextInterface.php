<?php

namespace Drupal\logbook;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a log text entity type.
 */
interface LogTextInterface extends ChildEntityInterface, ContentEntityInterface {

  /**
   * Gets the text.
   */
  public function getText(): string;

  /**
   * Sets the text.
   */
  public function setText(string $text): static;

  /**
   * Gets the public text.
   */
  public function getPublicText(bool $fallback = FALSE): string;

  /**
   * Sets the public text.
   */
  public function setPublicText(string $public_text): static;

}
