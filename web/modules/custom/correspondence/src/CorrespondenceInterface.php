<?php

namespace Drupal\correspondence;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for correspondence entities.
 */
interface CorrespondenceInterface extends ChildEntityInterface, ContentEntityInterface {

  /**
   * Gets the mailer subtype (policy id) that produced this entry.
   */
  public function getSubtype(): string;

  /**
   * Gets whether the mail was sent successfully.
   */
  public function isSent(): bool;

  /**
   * Gets whether the entry is a manager note.
   */
  public function isNote(): bool;

  /**
   * Gets the time the entry refers to (occurred, falling back to created).
   */
  public function getOccurredTime(): int;

}
