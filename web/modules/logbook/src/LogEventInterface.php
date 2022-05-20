<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a log event entity type.
 */
interface LogEventInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the log event creation timestamp.
   *
   * @return int
   *   Creation timestamp of the log event.
   */
  public function getCreatedTime();

  /**
   * Sets the log event creation timestamp.
   *
   * @param int $timestamp
   *   The log event creation timestamp.
   *
   * @return \Drupal\logbook\LogEventInterface
   *   The called log event entity.
   */
  public function setCreatedTime($timestamp);

}
