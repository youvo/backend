<?php

namespace Drupal\projects;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
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
   * @return \Drupal\projects\ProjectLifecycle
   *   The project workflow manager.
   */
  public function lifecycle();

  /**
   * Checks whether the user is an applicant.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $applicant
   *   The user or the uid.
   *
   * @return bool
   *   Is applicant?
   */
  public function isApplicant(AccountInterface|int $applicant);

  /**
   * Checks whether the project has an applicant.
   *
   * @return bool
   *   Has applicant?
   */
  public function hasApplicant();

  /**
   * Gets the applicants array keyed by UID.
   *
   * @return \Drupal\creatives\Entity\Creative[]
   *   The applicants.
   */
  public function getApplicants();

  /**
   * Sets the applicants to the project.
   *
   * @param \Drupal\Core\Session\AccountInterface[]|int[] $applicants
   *   The applicants.
   *
   * @return $this
   *   The current project.
   */
  public function setApplicants(array $applicants);

  /**
   * Appends an applicant to the project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $applicant
   *   The applicant or the uid.
   *
   * @return $this
   *   The current project.
   */
  public function appendApplicant(AccountInterface|int $applicant);

  /**
   * Checks whether the user is a participant.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $participant
   *   The user or the uid.
   *
   * @return bool
   *   Is participant?
   */
  public function isParticipant(AccountInterface|int $participant);

  /**
   * Checks whether the project has a participant.
   *
   * @return bool
   *   Has participant?
   */
  public function hasParticipant();

  /**
   * Gets the participants array keyed by UID.
   *
   * @return \Drupal\user\UserInterface[]
   *   The participants.
   */
  public function getParticipants();

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
  public function setParticipants(array $participants, array $tasks = []);

  /**
   * Appends a participant to the project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $participant
   *   The participant or the uid of the participant.
   * @param string $task
   *   The task of the participant. Defaults to creative.
   *
   * @return $this
   *   The current project.
   */
  public function appendParticipant(AccountInterface|int $participant, string $task = 'Creative');

  /**
   * Determines whether the account is the author (organization).
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account.
   *
   * @return bool
   *   Is author?
   */
  public function isAuthor(AccountInterface|int $account);

  /**
   * Gets the project title.
   *
   * @return string
   *   Title of the project.
   */
  public function getTitle(): string;

  /**
   * Sets the project title.
   *
   * @param string $title
   *   The project title.
   *
   * @return $this
   *   The called project entity.
   */
  public function setTitle(string $title): ProjectInterface;

  /**
   * Gets the project creation timestamp.
   *
   * @return int
   *   Creation timestamp of the project.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the project creation timestamp.
   *
   * @param int $timestamp
   *   The project creation timestamp.
   *
   * @return $this
   *   The called project entity.
   */
  public function setCreatedTime(int $timestamp): ProjectInterface;

  /**
   * Returns the project promotion status.
   *
   * @return bool
   *   TRUE if the project is promoted.
   */
  public function isPromoted(): bool;

  /**
   * Sets the project promoted status.
   *
   * @param bool $promoted
   *   TRUE to set this project to promoted, FALSE to set it to not promoted.
   *
   * @return $this
   *   The called project.
   */
  public function setPromoted(bool $promoted): ProjectInterface;

  /**
   * Gets the project result.
   *
   * @return \Drupal\projects\ProjectResultInterface
   *   The project results.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getResult(): ProjectResultInterface;

  /**
   * Returns the entity owner's user entity.
   *
   * Overwrite method for type hinting.
   *
   * @return \Drupal\organizations\Entity\Organization
   *   The organization user entity.
   */
  public function getOwner();

}
