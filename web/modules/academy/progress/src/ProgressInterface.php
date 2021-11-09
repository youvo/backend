<?php

namespace Drupal\progress;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a LectureProgress entity type.
 */
interface ProgressInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the Lecture enrollment timestamp.
   *
   * @return int
   *   Enrollment timestamp of the Lecture.
   */
  public function getEnrollmentTime();

  /**
   * Gets the Lecture access timestamp.
   *
   * @return int
   *   Access timestamp of the Lecture.
   */
  public function getAccessTime();

  /**
   * Sets the Lecture access timestamp.
   *
   * @param int $timestamp
   *   The Lecture access timestamp.
   *
   * @return \Drupal\progress\LectureProgressInterface
   *   The called LectureProgress entity.
   */
  public function setAccessTime(int $timestamp);

  /**
   * Gets the Lecture completed timestamp.
   *
   * @return int
   *   Completed timestamp of the Lecture.
   */
  public function getCompletedTime();

  /**
   * Sets the Lecture completed timestamp.
   *
   * @param int $timestamp
   *   The Lecture completed timestamp.
   *
   * @return \Drupal\progress\LectureProgressInterface
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
