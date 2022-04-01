<?php

namespace Drupal\projects;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Provides an interface defining a project node entity.
 */
interface ProjectInterface extends NodeInterface {

  /**
   * Checks whether user is an applicant.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $applicant
   *   The user or the uid.
   *
   * @return bool
   *   Is applicant?
   */
  public function isApplicant(AccountInterface|int $applicant);

  /**
   * Checks whether project has applicant.
   *
   * @return bool
   *   Has applicants?
   */
  public function hasApplicant();

  /**
   * Get applicants array keyed by UID.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of applicants.
   */
  public function getApplicants();

  /**
   * Set applicants to project.
   *
   * @param \Drupal\Core\Session\AccountInterface[]|int[]
   *   The applicants.
   */
  public function setApplicants(array $applicants);

  /**
   * Append applicant to project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int
   *   The applicant or the uid.
   */
  public function appendApplicant(AccountInterface|int $applicant);

  /**
   * Checks whether user is a participant.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $participant
   *   The user or the uid.
   *
   * @return bool
   *   Is applicant?
   */
  public function isParticipant(AccountInterface|int $participant);

  /**
   * Checks whether project has participant.
   *
   * @return bool
   *   Has applicants?
   */
  public function hasParticipant();

  /**
   * Get participants array keyed by UID.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of participant.
   */
  public function getParticipants();

  /**
   * Set participants to project.
   *
   * @param \Drupal\Core\Session\AccountInterface[]|int[] $participants
   *   The participants.
   * @param string[] $tasks
   *   Array of task per participant.
   */
  public function setParticipants(array $participants, array $tasks = []);

  /**
   * Append participant to project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $participant
   *   The participant or the uid of the participant.
   * @param string $task
   *   The task of the participant. Defaults to creative.
   */
  public function appendParticipant(AccountInterface|int $participant, string $task = 'Creative');

  /**
   * Determines whether account is author (organization) of this project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account.
   *
   * @return bool
   *   Is author?
   */
  public function isAuthor(AccountInterface|int $account);

  /**
   * Determines whether account is author (organization) or manager of this
   * project.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account.
   *
   * @return bool
   *   Is author or manager?
   */
  public function isAuthorOrManager(AccountInterface|int $account);

  /**
   * Get manager.
   *
   * @return \Drupal\user\UserInterface
   *   The Manager.
   */
  public function getManager();

}
