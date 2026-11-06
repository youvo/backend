<?php

namespace Drupal\projects\Event;

use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;

/**
 * Defines a project follow event, dispatched when a mail link is followed.
 */
class ProjectFollowEvent extends ProjectEventBase {

  /**
   * Constructs a ProjectFollowEvent object.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Drupal\user\UserInterface $recipient
   *   The recipient of the mail who followed the link.
   * @param string $subtype
   *   The full tag of the mail.
   * @param string $correspondence
   *   The UUID of the correspondence entry of the mail.
   * @param string $target
   *   The target key of the link.
   * @param int|null $timestamp
   *   The timestamp of the event.
   */
  public function __construct(
    ProjectInterface $project,
    protected UserInterface $recipient,
    protected string $subtype,
    protected string $correspondence,
    protected string $target,
    ?int $timestamp = NULL,
  ) {
    parent::__construct($project, $timestamp);
  }

  /**
   * Gets the recipient.
   */
  public function getRecipient(): UserInterface {
    return $this->recipient;
  }

  /**
   * Gets the full tag of the mail.
   */
  public function getSubtype(): string {
    return $this->subtype;
  }

  /**
   * Gets the UUID of the correspondence entry.
   */
  public function getCorrespondence(): string {
    return $this->correspondence;
  }

  /**
   * Gets the target key of the link.
   */
  public function getTarget(): string {
    return $this->target;
  }

}
