<?php

namespace Drupal\projects;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Interface that provides methods for a project node entity.
 */
interface ProjectInterface extends ContentEntityInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Denotes that the project is not published.
   */
  const NOT_PUBLISHED = 0;

  /**
   * Denotes that the project is published.
   */
  const PUBLISHED = 1;

  /**
   * Denotes that the project is not promoted to the front page.
   */
  const NOT_PROMOTED = 0;

  /**
   * Denotes that the project is promoted to the front page.
   */
  const PROMOTED = 1;

  /**
   * Calls project workflow manager which holds/manipulates the state.
   *
   * @return \Drupal\projects\Service\ProjectLifecycleInterface
   *   The project workflow manager.
   */
  public function lifecycle(): ProjectLifecycleInterface;

  /**
   * Checks whether the user is an applicant.
   */
  public function isApplicant(AccountInterface|int $applicant): bool;

  /**
   * Checks whether the project has an applicant.
   */
  public function hasApplicant(): bool;

  /**
   * Gets the applicants array keyed by UID.
   *
   * @return \Drupal\creatives\Entity\Creative[]
   *   The applicants.
   */
  public function getApplicants(): array;

  /**
   * Sets the applicants to the project.
   *
   * @param \Drupal\Core\Session\AccountInterface[]|int[] $applicants
   *   The applicants.
   *
   * @return $this
   *   The current project.
   */
  public function setApplicants(array $applicants): static;

  /**
   * Appends an applicant to the project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $applicant
   *   The applicant or the uid.
   *
   * @return $this
   *   The current project.
   */
  public function appendApplicant(AccountInterface|int $applicant): static;

  /**
   * Checks whether the user is a participant.
   */
  public function isParticipant(AccountInterface|int $participant): bool;

  /**
   * Checks whether the project has a participant.
   *
   * @param string|null $task
   *   Check whether the project has a participant with the given task.
   *
   * @return bool
   *   TRUE if participant found (with given task).
   */
  public function hasParticipant(?string $task = NULL): bool;

  /**
   * Gets the participants array keyed by UID.
   *
   * @return \Drupal\user\UserInterface[]
   *   The participants.
   */
  public function getParticipants(): array;

  /**
   * Sets the participants to the project.
   *
   * @param \Drupal\Core\Session\AccountInterface[]|int[] $participants
   *   The participants.
   * @param string[] $tasks
   *   The array of tasks per participant.
   *
   * @return $this
   *   The current project.
   */
  public function setParticipants(array $participants, array $tasks = []): static;

  /**
   * Appends a participant to the project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $participant
   *   The participant or the UID of the participant.
   * @param string $task
   *   The task of the participant. Defaults to creative.
   *
   * @return $this
   *   The current project.
   */
  public function appendParticipant(AccountInterface|int $participant, string $task = 'Creative'): static;

  /**
   * Determines whether the account is the author (organization).
   */
  public function isAuthor(AccountInterface|int $account): bool;

  /**
   * Gets the project title.
   */
  public function getTitle(): string;

  /**
   * Sets the project title.
   */
  public function setTitle(string $title): static;

  /**
   * Gets the project creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the project creation timestamp.
   */
  public function setCreatedTime(int $timestamp): static;

  /**
   * Returns the project promotion status.
   */
  public function isPromoted(): bool;

  /**
   * Sets the project promoted status.
   */
  public function setPromoted(bool $promoted): static;

  /**
   * Gets the project result.
   */
  public function getResult(): ProjectResultInterface;

  /**
   * Returns the entity owner's user entity.
   *
   * Overwrite method for type hinting.
   */
  public function getOwner(): Organization;

}
