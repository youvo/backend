<?php

namespace Drupal\projects\Event;

use Drupal\projects\ProjectInterface;

/**
 * Defines a project change event.
 *
 * Dispatched when a save changes content fields of a project past draft.
 * Carries the changed fields and the revisions before and after the save.
 */
class ProjectChangeEvent extends ProjectEventBase {

  /**
   * Constructs a ProjectChangeEvent object.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param string[] $fields
   *   The changed content fields.
   * @param int $previousRevisionId
   *   The revision before the change.
   * @param int $revisionId
   *   The revision the change created.
   * @param int|null $timestamp
   *   The time of the change.
   */
  public function __construct(
    ProjectInterface $project,
    protected array $fields,
    protected int $previousRevisionId,
    protected int $revisionId,
    ?int $timestamp = NULL,
  ) {
    parent::__construct($project, $timestamp);
  }

  /**
   * Gets the changed content fields.
   *
   * @return string[]
   *   The field names, in ProjectInterface::CONTENT_FIELDS order.
   */
  public function getFields(): array {
    return $this->fields;
  }

  /**
   * Gets the revision ID before the change.
   */
  public function getPreviousRevisionId(): int {
    return $this->previousRevisionId;
  }

  /**
   * Gets the revision ID the change created.
   */
  public function getRevisionId(): int {
    return $this->revisionId;
  }

}
