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
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   */
  public function __construct(protected ProjectInterface $project) {}

  /**
   * Gets the project.
   */
  public function getProject(): ProjectInterface {
    return $this->project;
  }

}
