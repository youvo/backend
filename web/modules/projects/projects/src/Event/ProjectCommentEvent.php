<?php

namespace Drupal\projects\Event;

/**
 * Defines a project comment event.
 */
class ProjectCommentEvent extends ProjectEventBase {

  /**
   * The comment.
   *
   * @var string
   */
  protected string $comment;

  /**
   * Gets the comment.
   */
  public function getComment(): string {
    return $this->comment ?? '';
  }

  /**
   * Sets the comment.
   */
  public function setComment(string $comment): ProjectCommentEvent {
    $this->comment = $comment;
    return $this;
  }

}
