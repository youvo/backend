<?php

namespace Drupal\courses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

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
  public function setTitle(string $title);

  /**
   * Gets the course machine name.
   *
   * @return string
   *   Machine name of the course.
   */
  public function getMachineName();

  /**
   * Sets the course machine name.
   *
   * @param string $machine_name
   *   The course title.
   *
   * @return \Drupal\courses\CourseInterface
   *   The called course entity.
   */
  public function setMachineName(string $machine_name);

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
  public function setCreatedTime(int $timestamp);

}
