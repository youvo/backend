<?php

namespace Drupal\projects\Event;

use Drupal\projects\ProjectDeclineReason;

/**
 * Defines a project decline event.
 *
 * Carries the reason and the edited mail.
 */
class ProjectDeclineEvent extends ProjectEventBase {

  use ProjectMailEventTrait;

  /**
   * The reason for declining the project.
   */
  protected ProjectDeclineReason $reason = ProjectDeclineReason::Custom;

  /**
   * Gets the reason.
   */
  public function getReason(): ProjectDeclineReason {
    return $this->reason;
  }

  /**
   * Sets the reason.
   */
  public function setReason(ProjectDeclineReason $reason): static {
    $this->reason = $reason;
    return $this;
  }

}
