<?php

namespace Drupal\mailer\Manager;

use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;

/**
 * Registry of the mails a manager may send per project state.
 */
class ManagerMailRegistry {

  /**
   * Gets all definitions.
   *
   * @return \Drupal\mailer\Manager\ManagerMailDefinition[]
   *   The definitions in display order.
   */
  protected function definitions(): array {
    return [
      new ManagerMailDefinition('reminder_draft', 'Erinnerung: Projekt einreichen', [ProjectState::Draft]),
      new ManagerMailDefinition('reminder_open', 'Erinnerung: Bewerbungen sichten', [ProjectState::Open], static fn (ProjectInterface $p) => $p->hasApplicant()),
      new ManagerMailDefinition('reminder_open_noone', 'Erinnerung: Projekt ist online', [ProjectState::Open], static fn (ProjectInterface $p) => !$p->hasApplicant()),
      new ManagerMailDefinition('reminder_ongoing', 'Erinnerung: Projekt abschließen', [ProjectState::Ongoing]),
    ];
  }

  /**
   * Gets the lifecycle state of a project.
   */
  public function stateOf(ProjectInterface $project): ProjectState {
    $lifecycle = $project->lifecycle();
    return match (TRUE) {
      $lifecycle->isDraft() => ProjectState::Draft,
      $lifecycle->isPending() => ProjectState::Pending,
      $lifecycle->isOpen() => ProjectState::Open,
      $lifecycle->isOngoing() => ProjectState::Ongoing,
      $lifecycle->isCompleted() => ProjectState::Completed,
      default => ProjectState::Declined,
    };
  }

  /**
   * Gets the definitions offered for the project in its current state.
   *
   * @return \Drupal\mailer\Manager\ManagerMailDefinition[]
   *   The definitions.
   */
  public function available(ProjectInterface $project): array {
    $state = $this->stateOf($project);
    return array_values(array_filter($this->definitions(), static fn (ManagerMailDefinition $d) => $d->appliesTo($project, $state)));
  }

  /**
   * Finds a definition if it is offered for the project's current state.
   */
  public function find(ProjectInterface $project, string $subtype): ?ManagerMailDefinition {
    foreach ($this->available($project) as $definition) {
      if ($definition->subtype === $subtype) {
        return $definition;
      }
    }
    return NULL;
  }

}
