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
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   *
   * @return bool
   *   Is applicant?
   */
  public function isApplicant(AccountInterface $account);

  /**
   * Checks whether project has applicants.
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
   * @param \Drupal\Core\Session\AccountInterface[]
   *   The applicants.
   */
  public function setApplicants(array $applicants);

  /**
   * Append applicant to project.
   *
   * @param \Drupal\Core\Session\AccountInterface
   *   The applicant.
   */
  public function appendApplicant(AccountInterface $applicant);

}
