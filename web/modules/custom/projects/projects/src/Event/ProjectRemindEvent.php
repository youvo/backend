<?php

namespace Drupal\projects\Event;

use Drupal\projects\ProjectInterface;

/**
 * Defines a project remind event, dispatched when a manager reminds.
 */
class ProjectRemindEvent extends ProjectEventBase {

  /**
   * Constructs a ProjectRemindEvent object.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param string $subtype
   *   The full tag of the mail.
   * @param string $correspondence
   *   The UUID of the correspondence entry of the mail.
   * @param int|null $timestamp
   *   The timestamp of the event.
   */
  public function __construct(
    ProjectInterface $project,
    protected string $subtype,
    protected string $correspondence,
    ?int $timestamp = NULL,
  ) {
    parent::__construct($project, $timestamp);
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

}
