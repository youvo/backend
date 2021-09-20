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

}
