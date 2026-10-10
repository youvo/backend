<?php

namespace Drupal\projects\Event;

use Drupal\projects\ProjectArchiveReason;

/**
 * Defines a project archive event.
 */
class ProjectArchiveEvent extends ProjectEventBase {

  /**
   * The reason for archiving the project.
   */
  protected ?ProjectArchiveReason $reason = NULL;

  /**
   * The message.
   */
  protected string $message = '';

  /**
   * Gets the reason.
   */
  public function getReason(): ?ProjectArchiveReason {
    return $this->reason;
  }

  /**
   * Sets the reason.
   */
  public function setReason(?ProjectArchiveReason $reason): static {
    $this->reason = $reason;
    return $this;
  }

  /**
   * Gets the message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Sets the message.
   */
  public function setMessage(string $message): static {
    $this->message = $message;
    return $this;
  }

}
