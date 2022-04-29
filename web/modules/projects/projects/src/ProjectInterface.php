<?php

namespace Drupal\projects;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Interface that provides methods for a project node entity.
 */
interface ProjectInterface extends NodeInterface {

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
   * @return \Drupal\user\UserInterface[]
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
   * Determines whether the account is the author (organization) or the manager.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account.
   *
   * @return bool
   *   Is author or manager?
   */
  public function isAuthorOrManager(AccountInterface|int $account);

  /**
   * Determines whether the account is the manager of the organization.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account.
   *
   * @return bool
   *   Is manager?
   */
  public function isManager(AccountInterface|int $account);

  /**
   * Gets the manager.
   *
   * @return \Drupal\user\UserInterface|null
   *   The manager.
   */
  public function getManager();

}
