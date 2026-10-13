<?php

namespace Drupal\correspondence;

use Drupal\child_entities\ChildEntityInterface;

/**
 * Provides an interface for correspondence entities.
 */
interface CorrespondenceInterface extends ChildEntityInterface {

  /**
   * Gets the mailer subtype (policy id) that produced this entry.
   */
  public function getSubtype(): string;

  /**
   * Gets whether the mail was sent successfully.
   */
  public function isSent(): bool;

}
