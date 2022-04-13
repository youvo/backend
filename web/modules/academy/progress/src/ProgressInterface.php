<?php

namespace Drupal\progress;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a lecture progress entity.
 */
interface ProgressInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the lecture enrollment timestamp.
   *
   * @return int
   *   Enrollment timestamp of the lecture.
   */
  public function getEnrollmentTime();

  /**
   * Gets the lecture access timestamp.
   *
   * @return int
   *   Access timestamp of the lecture.
   */
  public function getAccessTime();

  /**
   * Sets the lecture access timestamp.
   *
   * @param int $timestamp
   *   The lecture access timestamp.
   *
   * @return \Drupal\progress\ProgressInterface
   *   The lecture progress entity.
   */
  public function setAccessTime(int $timestamp);

  /**
   * Gets the lecture completed timestamp.
   *
   * @return int
   *   Completed timestamp of the lecture.
   */
  public function getCompletedTime();

  /**
   * Sets the lecture completed timestamp.
   *
   * @param int $timestamp
   *   The Lecture completed timestamp.
   *
   * @return \Drupal\progress\ProgressInterface
   *   The called LectureProgress entity.
   */
  public function setCompletedTime(int $timestamp);

  /**
   * Returns the entity owner's user entity.
   *
   * @return \Drupal\user\UserInterface
   *   The owner user entity.
   */
  public function getOwner();

  /**
   * Returns the entity owner's user ID.
   *
   * @return int|null
   *   The owner user ID, or NULL in case the user ID field has not been set on
   *   the entity.
   */
  public function getOwnerId();

}
