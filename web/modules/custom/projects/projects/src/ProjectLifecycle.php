<?php

namespace Drupal\projects;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;

/**
 * Provides methods to manage the workflow of a project.
 */
class ProjectLifecycle {

  const WORKFLOW_ID = 'project_lifecycle';
  const LIFECYCLE_FIELD = 'field_lifecycle';

  /**
   * The project calling the lifecycle.
   *
   * @var \Drupal\projects\ProjectInterface|null
   */
  protected ?ProjectInterface $project = NULL;

  /**
   * Constructs a ProjectLifecycle object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Sets the project.
   */
  public function setProject(ProjectInterface $project): static {
    $this->project = $project;
    return $this;
  }

  /**
   * Gets the project.
   */
  public function project(): ProjectInterface {
    if (isset($this->project)) {
      return $this->project;
    }
    throw new \UnexpectedValueException('Project not set properly in workflow manager.');
  }

  /**
   * Gets the current state of the project.
   */
  protected function getState(): ProjectState {
    $state = $this->project()->get(static::LIFECYCLE_FIELD)->value;
    return ProjectState::from($state);
  }

  /**
   * Checks if the project is a draft.
   */
  public function isDraft(): bool {
    return $this->getState() === ProjectState::DRAFT;
  }

  /**
   * Checks if the project is pending.
   */
  public function isPending(): bool {
    return $this->getState() === ProjectState::PENDING;
  }

  /**
   * Checks if the project is open.
   */
  public function isOpen(): bool {
    return $this->getState() === ProjectState::OPEN;
  }

  /**
   * Checks if the project is ongoing.
   */
  public function isOngoing(): bool {
    return $this->getState() === ProjectState::ONGOING;
  }

  /**
   * Checks if the project is completed.
   */
  public function isCompleted(): bool {
    return $this->getState() === ProjectState::COMPLETED;
  }

  /**
   * Checks if the project can transition by transition label.
   */
  public function canTransition(ProjectTransition $transition): bool {
    if ($transition === ProjectTransition::MEDIATE) {
      return $this->project()->hasApplicant() &&
        $this->hasTransition($transition);
    }
    return $this->hasTransition($transition);
  }

  /**
   * Submits the project.
   */
  public function submit(): bool {
    return $this->doTransition(ProjectTransition::SUBMIT);
  }

  /**
   * Publishes the project.
   */
  public function publish(): bool {
    return $this->doTransition(ProjectTransition::PUBLISH);
  }

  /**
   * Mediates the project.
   */
  public function mediate(): bool {
    return $this->doTransition(ProjectTransition::MEDIATE);
  }

  /**
   * Completes the project.
   */
  public function complete(): bool {
    return $this->doTransition(ProjectTransition::COMPLETE);
  }

  /**
   * Resets the project.
   */
  public function reset(): bool {
    return $this->doTransition(ProjectTransition::RESET);
  }

  /**
   * Abstraction of forward transition flow check.
   */
  protected function hasTransition(ProjectTransition $transition): bool {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->entityTypeManager->getStorage('workflow')->load(static::WORKFLOW_ID);
    return $workflow->getTypePlugin()->hasTransition($transition->value);
  }

  /**
   * Sets new project state for given transition.
   */
  protected function doTransition(ProjectTransition $transition): bool {
    if ($this->canTransition($transition)) {
      $new_state = $this->getSuccessorFromTransition($transition);
      $this->project()->set(static::LIFECYCLE_FIELD, $new_state);
      return TRUE;
    }
    throw new LifecycleTransitionException($transition->value);
  }

  /**
   * Gets new state assuming linear transition flow.
   */
  protected function getSuccessorFromTransition(ProjectTransition $transition): ProjectState {
    return match ($transition) {
      ProjectTransition::SUBMIT => ProjectState::PENDING,
      ProjectTransition::PUBLISH => ProjectState::OPEN,
      ProjectTransition::MEDIATE => ProjectState::ONGOING,
      ProjectTransition::COMPLETE => ProjectState::COMPLETED,
      // All other transitions, including reset, set the project state to draft.
      default => ProjectState::DRAFT,
    };
  }

}
