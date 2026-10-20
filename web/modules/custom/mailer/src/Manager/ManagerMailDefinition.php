<?php

namespace Drupal\mailer\Manager;

use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;

/**
 * Describes one mail a manager may send.
 */
final class ManagerMailDefinition {

  /**
   * Constructs a definition.
   *
   * @param string $subtype
   *   Subtype of the mailer.manager base tag.
   * @param string $label
   *   Label shown in the pane (German).
   * @param \Drupal\projects\ProjectState[] $states
   *   States in which the mail is offered.
   * @param \Closure|null $condition
   *   Optional extra condition on the project.
   * @param bool $decline
   *   Whether the mail declines a project proposal.
   */
  public function __construct(
    public readonly string $subtype,
    public readonly string $label,
    public readonly array $states,
    protected ?\Closure $condition = NULL,
    public readonly bool $decline = FALSE,
  ) {}

  /**
   * Whether the mail is offered for the project in the given state.
   */
  public function appliesTo(ProjectInterface $project, ProjectState $state): bool {
    return in_array($state, $this->states, TRUE) && ($this->condition === NULL || ($this->condition)($project));
  }

}
