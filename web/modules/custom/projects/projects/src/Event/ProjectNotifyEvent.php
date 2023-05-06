<?php

namespace Drupal\projects\Event;

/**
 * Defines a project notify event.
 */
class ProjectNotifyEvent extends ProjectEventBase {

  /**
   * A follow-up link.
   *
   * @var string
   */
  protected string $link;

  /**
   * Gets the follow-up link.
   */
  public function getLink(): string {
    return $this->link ?? '';
  }

  /**
   * Sets the follow-up link.
   */
  public function setLink(string $link): ProjectNotifyEvent {
    $this->link = $link;
    return $this;
  }

}
