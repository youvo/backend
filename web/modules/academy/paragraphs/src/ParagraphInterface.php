<?php

namespace Drupal\paragraphs;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a paragraph entity type.
 */
interface ParagraphInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the paragraph title.
   *
   * @return string
   *   Title of the paragraph.
   */
  public function getTitle();

  /**
   * Sets the paragraph title.
   *
   * @param string $title
   *   The paragraph title.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The called paragraph entity.
   */
  public function setTitle(string $title);

  /**
   * Gets the paragraph creation timestamp.
   *
   * @return int
   *   Creation timestamp of the paragraph.
   */
  public function getCreatedTime();

  /**
   * Sets the paragraph creation timestamp.
   *
   * @param int $timestamp
   *   The paragraph creation timestamp.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The called paragraph entity.
   */
  public function setCreatedTime(int $timestamp);

  /**
   * Returns the paragraph status.
   *
   * @return bool
   *   TRUE if the paragraph is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the paragraph status.
   *
   * @param bool $status
   *   TRUE to enable this paragraph, FALSE to disable.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The called paragraph entity.
   */
  public function setStatus(bool $status);

}
