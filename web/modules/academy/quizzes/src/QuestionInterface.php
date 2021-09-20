<?php

namespace Drupal\quizzes;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a question entity type.
 */
interface QuestionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the question creation timestamp.
   *
   * @return int
   *   Creation timestamp of the question.
   */
  public function getCreatedTime();

  /**
   * Sets the question creation timestamp.
   *
   * @param int $timestamp
   *   The question creation timestamp.
   *
   * @return \Drupal\quizzes\QuestionInterface
   *   The called question entity.
   */
  public function setCreatedTime($timestamp);

}
