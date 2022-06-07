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
  public function getCreatedTime(): int;

  /**
   * Sets the log event creation timestamp.
   *
   * @param int $timestamp
   *   The log event creation timestamp.
   *
   * @return \Drupal\logbook\LogEventInterface
   *   The called log event entity.
   */
  public function setCreatedTime(int $timestamp): LogEventInterface;

  /**
   * Gets the subject.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The subject.
   */
  public function getSubject(): ?ContentEntityInterface;

  /**
   * Sets the subject.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $subject
   *   The subject.
   *
   * @return \Drupal\logbook\LogEventInterface
   *   The current log event.
   */
  public function setSubject(ContentEntityInterface $subject): LogEventInterface;

}
