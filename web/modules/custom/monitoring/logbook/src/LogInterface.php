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
   */
  public function getCreatedTime(): int;

  /**
   * Sets the log creation timestamp.
   */
  public function setCreatedTime(int $timestamp): static;

  /**
   * Checks whether the log has a project.
   */
  public function hasProject(): bool;

  /**
   * Gets the project.
   */
  public function getProject(): ?ProjectInterface;

  /**
   * Sets the project.
   */
  public function setProject(ProjectInterface|int $project): static;

  /**
   * Checks whether the log has an organization.
   */
  public function hasOrganization(): bool;

  /**
   * Gets the organization.
   */
  public function getOrganization(): ?Organization;

  /**
   * Sets the organization.
   */
  public function setOrganization(Organization|int $organization): static;

  /**
   * Checks whether the log has a creative.
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
   * @return $this
   *   The current log.
   */
  public function setCreatives(array $creatives): static;

  /**
   * Checks whether the log has a manager.
   */
  public function hasManager(): bool;

  /**
   * Gets the manager.
   */
  public function getManager(): ?Creative;

  /**
   * Sets the manager.
   */
  public function setManager(AccountInterface|int $manager): static;

  /**
   * Gets the message.
   */
  public function getMessage(): string;

  /**
   * Sets the message.
   */
  public function setMessage(string $message): static;

  /**
   * Gets the decoded miscellaneous information.
   */
  public function getMisc(): array;

  /**
   * Sets the miscellaneous information as JSON encoded string.
   */
  public function setMisc(array $misc): static;

  /**
   * Gets the computed markup text.
   */
  public function getMarkup(): string;

  /**
   * Gets the log pattern entity.
   */
  public function getPattern(): LogPatternInterface;

  /**
   * Returns the entity owner's user entity.
   *
   * Overwrite method for type hinting.
   */
  public function getOwner(): Creative|Organization;

  /**
   * Gets the background color.
   */
  public function getColor(): string;

  /**
   * Sets the background color.
   */
  public function setColor(string $color): static;

}
