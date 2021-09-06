<?php

namespace Drupal\youvo_academy;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a lecture entity type.
 */
interface LectureInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the lecture title.
   *
   * @return string
   *   Title of the lecture.
   */
  public function getTitle();

  /**
   * Sets the lecture title.
   *
   * @param string $title
   *   The lecture title.
   *
   * @return \Drupal\youvo_academy\LectureInterface
   *   The called lecture entity.
   */
  public function setTitle($title);

  /**
   * Gets the lecture creation timestamp.
   *
   * @return int
   *   Creation timestamp of the lecture.
   */
  public function getCreatedTime();

  /**
   * Sets the lecture creation timestamp.
   *
   * @param int $timestamp
   *   The lecture creation timestamp.
   *
   * @return \Drupal\youvo_academy\LectureInterface
   *   The called lecture entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the lecture status.
   *
   * @return bool
   *   TRUE if the lecture is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the lecture status.
   *
   * @param bool $status
   *   TRUE to enable this lecture, FALSE to disable.
   *
   * @return \Drupal\youvo_academy\LectureInterface
   *   The called lecture entity.
   */
  public function setStatus($status);

}
