<?php

namespace Drupal\projects\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\ProjectInterface;

/**
 * Defines a base class for project events.
 */
abstract class ProjectEventBase extends Event {

  /**
   * Constructs a ProjectEventBase object.
   */
  public function __construct(
    protected ProjectInterface $project,
    protected ?int $timestamp = NULL,
  ) {}

  /**
   * Gets the project.
   */
  public function getProject(): ProjectInterface {
    return $this->project;
  }

  /**
   * Gets the timestamp for the event.
   *
   * Usually this is only used for creating fake content. Rely on the request
   * time otherwise.
   */
  public function getTimestamp(): ?int {
    return $this->timestamp;
  }

}
