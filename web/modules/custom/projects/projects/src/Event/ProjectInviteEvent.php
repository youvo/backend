<?php

namespace Drupal\projects\Event;

/**
 * Defines a project invite event.
 */
class ProjectInviteEvent extends ProjectEventBase {

  /**
   * An invitation link.
   */
  protected string $link = '';

  /**
   * Gets the invite link.
   */
  public function getLink(): string {
    return $this->link;
  }

  /**
   * Sets the invite link.
   */
  public function setLink(string $link): static {
    $this->link = $link;
    return $this;
  }

}
