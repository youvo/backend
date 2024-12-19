<?php

namespace Drupal\projects\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;

/**
 * Provides methods to manage the workflow of a project.
 */
class ProjectLifecycle implements ProjectLifecycleInterface {

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
   * {@inheritdoc}
   */
  public function setProject(ProjectInterface $project): static {
    $this->project = $project;
    return $this;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function isDraft(): bool {
    return $this->getState() === ProjectState::DRAFT;
  }

  /**
   * {@inheritdoc}
   */
  public function isPending(): bool {
    return $this->getState() === ProjectState::PENDING;
  }

  /**
   * {@inheritdoc}
   */
  public function isOpen(): bool {
    return $this->getState() === ProjectState::OPEN;
  }

  /**
   * {@inheritdoc}
   */
  public function isOngoing(): bool {
    return $this->getState() === ProjectState::ONGOING;
  }

  /**
   * {@inheritdoc}
   */
  public function isCompleted(): bool {
    return $this->getState() === ProjectState::COMPLETED;
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
   * Checks if the project can perform the given transition.
   */
  protected function canTransition(ProjectTransition $transition): bool {
    if ($transition === ProjectTransition::MEDIATE || $transition === ProjectTransition::COMPLETE) {
      return $this->project()->hasParticipant('Creative');
    }
    return $this->hasTransition($transition);
  }

  /**
   * Sets new project state for given transition.
   */
  protected function doTransition(ProjectTransition $transition): bool {
    if ($this->canTransition($transition)) {
      $new_state = $this->getSuccessorFromTransition($transition);
      $this->project()->set(static::LIFECYCLE_FIELD, $new_state->value);
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
