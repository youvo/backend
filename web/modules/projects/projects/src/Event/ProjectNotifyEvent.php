<?php

namespace Drupal\projects\Event;

/**
 * Defines a project notify event.
 */
class ProjectNotifyEvent extends ProjectEventBase {

  /**
   * An invitation link.
   *
   * @var string
   */
  protected string $invitationLink;

  /**
   * Gets the invitation link.
   */
  public function getInvitationLink(): string {
    return $this->invitationLink ?? '';
  }

  /**
   * Sets the invitation link.
   */
  public function setInvitationLink(string $invitation_link): ProjectNotifyEvent {
    $this->invitationLink = $invitation_link;
    return $this;
  }

}
