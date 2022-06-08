<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
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
   * @return \Drupal\logbook\LogEventInterface
   *   The current log event.
   */
  public function setProject(ProjectInterface $project): LogEventInterface;

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
   * @return \Drupal\logbook\LogEventInterface
   *   The current log event.
   */
  public function setCreatives(array $creatives): LogEventInterface;

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
   * @return \Drupal\logbook\LogEventInterface
   *   The current log event.
   */
  public function setManager(AccountInterface $manager): LogEventInterface;

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
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The organization.
   *
   * @return \Drupal\logbook\LogEventInterface
   *   The current log event.
   */
  public function setOrganization(Organization $organization): LogEventInterface;

}
