<?php

namespace Drupal\courses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a course entity type.
 */
interface CourseInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the course title.
   *
   * @return string
   *   Title of the course.
   */
  public function getTitle();

  /**
   * Sets the course title.
   *
   * @param string $title
   *   The course title.
   *
   * @return \Drupal\courses\CourseInterface
   *   The called course entity.
   */
  public function setTitle($title);

  /**
   * Gets the course creation timestamp.
   *
   * @return int
   *   Creation timestamp of the course.
   */
  public function getCreatedTime();

  /**
   * Sets the course creation timestamp.
   *
   * @param int $timestamp
   *   The course creation timestamp.
   *
   * @return \Drupal\courses\CourseInterface
   *   The called course entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the course status.
   *
   * @return bool
   *   TRUE if the course is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the course status.
   *
   * @param bool $status
   *   TRUE to enable this course, FALSE to disable.
   *
   * @return \Drupal\courses\CourseInterface
   *   The called course entity.
   */
  public function setStatus($status);

}
