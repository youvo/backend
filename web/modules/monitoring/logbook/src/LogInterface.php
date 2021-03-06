<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a log entity type.
 */
interface LogInterface extends ContentEntityInterface, EntityOwnerInterface, EntityPublishedInterface {

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
   * @param \Drupal\projects\ProjectInterface|int $project
   *   The project or the project ID.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setProject(ProjectInterface|int $project): LogInterface;

  /**
   * Checks whether the log has an organization.
   *
   * @return bool
   *   Has organization?
   */
  public function hasOrganization(): bool;

  /**
   * Gets the organization.
   *
   * @return \Drupal\organizations\Entity\Organization|null
   *   The organization.
   */
  public function getOrganization(): ?Organization;

  /**
   * Sets the organization.
   *
   * @param \Drupal\organizations\Entity\Organization|int $organization
   *   The organization or the organization ID.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setOrganization(Organization|int $organization): LogInterface;

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
   * @param \Drupal\Core\Session\AccountInterface|int $manager
   *   The manager or the manager ID.
   *
   * @return \Drupal\logbook\LogInterface
   *   The current log.
   */
  public function setManager(AccountInterface|int $manager): LogInterface;

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

  /**
   * Gets the background color.
   *
   * @return string
   *   The background color.
   */
  public function getColor(): string;

  /**
   * Sets the background color.
   *
   * @param string $color
   *   The background color.
   *
   * @return \Drupal\logbook\LogInterface
   *   The called log entity.
   */
  public function setColor(string $color): LogInterface;

}
