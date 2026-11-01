<?php

namespace Drupal\projects\Event;

use Drupal\projects\ProjectArchiveReason;

/**
 * Defines a project archive event.
 *
 * Carries the reason and the edited mail.
 */
class ProjectArchiveEvent extends ProjectEventBase {

  use ProjectMailEventTrait;

  /**
   * The reason for archiving the project.
   */
  protected ProjectArchiveReason $reason = ProjectArchiveReason::Custom;

  /**
   * Gets the reason.
   */
  public function getReason(): ProjectArchiveReason {
    return $this->reason;
  }

  /**
   * Sets the reason.
   */
  public function setReason(ProjectArchiveReason $reason): static {
    $this->reason = $reason;
    return $this;
  }

}
