<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a log entity type.
 */
interface LogInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the log creation timestamp.
   *
   * @return int
   *   Creation timestamp of the log.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the log creation timestamp.
   *
   * @param int $timestamp
   *   The log creation timestamp.
   *
   * @return \Drupal\logbook\LogInterface
   *   The called log entity.
   */
  public function setCreatedTime(int $timestamp): LogInterface;

  /**
   * Checks whether the log has a project.
   *
   * @return bool
   *   Has project?
   */
  public function hasProject(): bool;

  /**
   * Gets the project.
   *
   * @return \Drupal\projects\ProjectInterface|null
   *   The project.
   */
  public function getProject(): ?ProjectInterface;

  /**
   * Sets the project.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setProject(ProjectInterface $project): LogInterface;

  /**
   * Checks whether the log has a creative.
   *
   * @return bool
   *   Has creatives?
   */
  public function hasCreatives(): bool;

  /**
   * Gets the creatives array keyed by UID.
   *
   * @return \Drupal\creatives\Entity\Creative[]
   *   The creatives.
   */
  public function getCreatives(): array;

  /**
   * Sets the creatives.
   *
   * @param \Drupal\Core\Session\AccountInterface[]|int[] $creatives
   *   The creatives or the creatives IDs.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setCreatives(array $creatives): LogInterface;

  /**
   * Checks whether the log has a manager.
   *
   * @return bool
   *   Has manager?
   */
  public function hasManager(): bool;

  /**
   * Gets the subject.
   *
   * @return \Drupal\creatives\Entity\Creative|null
   *   The subject.
   */
  public function getManager(): ?Creative;

  /**
   * Sets the manager.
   *
   * @param \Drupal\Core\Session\AccountInterface $manager
   *   The manager.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setManager(AccountInterface $manager): LogInterface;

  /**
   * Gets the message.
   *
   * @return string
   *   The message.
   */
  public function getMessage(): string;

  /**
   * Sets the message.
   *
   * @param string $message
   *   The message.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setMessage(string $message): LogInterface;

  /**
   * Gets the decoded miscellaneous information.
   *
   * @return array
   *   The miscellaneous information.
   */
  public function getMisc(): array;

  /**
   * Sets the miscellaneous information as JSON encoded string.
   *
   * @param array $misc
   *   The miscellaneous information.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setMisc(array $misc): LogInterface;

  /**
   * Gets the computed markup text.
   *
   * @return string
   *   The markup.
   */
  public function getMarkup(): string;

  /**
   * Gets the log pattern entity.
   *
   * @return LogPatternInterface
   *   The log pattern.
   */
  public function getPattern(): LogPatternInterface;

  /**
   * Returns the entity owner's user entity.
   *
   * Overwrite method for type hinting.
   *
   * @return \Drupal\creatives\Entity\Creative|\Drupal\organizations\Entity\Organization
   *   The organization or creative user entity.
   */
  public function getOwner();

}
