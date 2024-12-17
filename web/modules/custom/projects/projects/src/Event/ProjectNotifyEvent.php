<?php

namespace Drupal\projects\Event;

/**
 * Defines a project notify event.
 */
class ProjectNotifyEvent extends ProjectEventBase {

  /**
   * A follow-up link.
   */
  protected string $link = '';

  /**
   * Gets the follow-up link.
   */
  public function getLink(): string {
    return $this->link;
  }

  /**
   * Sets the follow-up link.
   */
  public function setLink(string $link): static {
    $this->link = $link;
    return $this;
  }

}
