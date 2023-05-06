<?php

namespace Drupal\projects\Event;

/**
 * Defines a project invite event.
 */
class ProjectInviteEvent extends ProjectEventBase {

  /**
   * A invite link.
   *
   * @var string
   */
  protected string $link;

  /**
   * Gets the invite link.
   */
  public function getLink(): string {
    return $this->link ?? '';
  }

  /**
   * Sets the invite link.
   */
  public function setLink(string $link): ProjectInviteEvent {
    $this->link = $link;
    return $this;
  }

}
