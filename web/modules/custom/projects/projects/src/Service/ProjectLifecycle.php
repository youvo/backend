<?php

namespace Drupal\projects\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
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
  const LIFECYCLE_HISTORY_FIELD = 'field_lifecycle_history';

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
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
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
    return $this->getState() === ProjectState::Draft;
  }

  /**
   * {@inheritdoc}
   */
  public function isPending(): bool {
    return $this->getState() === ProjectState::Pending;
  }

  /**
   * {@inheritdoc}
   */
  public function isOpen(): bool {
    return $this->getState() === ProjectState::Open;
  }

  /**
   * {@inheritdoc}
   */
  public function isOngoing(): bool {
    return $this->getState() === ProjectState::Ongoing;
  }

  /**
   * {@inheritdoc}
   */
  public function isCompleted(): bool {
    return $this->getState() === ProjectState::Completed;
  }

  /**
   * Submits the project.
   */
  public function submit(?int $timestamp = NULL): bool {
    return $this->doTransition(ProjectTransition::Submit, $timestamp);
  }

  /**
   * Publishes the project.
   */
  public function publish(?int $timestamp = NULL): bool {
    return $this->doTransition(ProjectTransition::Publish, $timestamp);
  }

  /**
   * Mediates the project.
   */
  public function mediate(?int $timestamp = NULL): bool {
    return $this->doTransition(ProjectTransition::Mediate, $timestamp);
  }

  /**
   * Completes the project.
   */
  public function complete(?int $timestamp = NULL): bool {
    return $this->doTransition(ProjectTransition::Complete, $timestamp);
  }

  /**
   * Resets the project.
   */
  public function reset(?int $timestamp = NULL): bool {
    return $this->doTransition(ProjectTransition::Reset, $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function history(): FieldItemListInterface {
    return $this->project()->get(static::LIFECYCLE_HISTORY_FIELD);
  }

  /**
   * Abstraction of forward transition flow check.
   */
  protected function hasTransition(ProjectState $from, ProjectState $to): bool {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->entityTypeManager
      ->getStorage('workflow')
      ->load(static::WORKFLOW_ID);
    return $workflow->getTypePlugin()
      ->hasTransitionFromStateToState($from->value, $to->value);
  }

  /**
   * Checks if the project can perform the given transition.
   */
  protected function canTransition(ProjectTransition $transition, ProjectState $from, ProjectState $to): bool {
    if ($transition === ProjectTransition::Mediate || $transition === ProjectTransition::Complete) {
      return $this->project()->hasParticipant('Creative') && $this->hasTransition($from, $to);
    }
    return $this->hasTransition($from, $to);
  }

  /**
   * Sets new project state for given transition.
   */
  protected function doTransition(ProjectTransition $transition, ?int $timestamp = NULL): bool {
    $old_state = $this->getState();
    $new_state = $this->getSuccessorFromTransition($transition);
    if ($this->canTransition($transition, $old_state, $new_state)) {
      $this->project()->set(static::LIFECYCLE_FIELD, $new_state->value);
      $this->inscribeTransition($transition, $old_state, $new_state, $timestamp);
      return TRUE;
    }
    throw new LifecycleTransitionException($transition->value);
  }

  /**
   * Gets new state assuming linear transition flow.
   */
  protected function getSuccessorFromTransition(ProjectTransition $transition): ProjectState {
    return match ($transition) {
      ProjectTransition::Submit => ProjectState::Pending,
      ProjectTransition::Publish => ProjectState::Open,
      ProjectTransition::Mediate => ProjectState::Ongoing,
      ProjectTransition::Complete => ProjectState::Completed,
      // All other transitions, including reset, set the project state to draft.
      default => ProjectState::Draft,
    };
  }

  /**
   * Inscribes transition in lifecycle history.
   */
  protected function inscribeTransition(ProjectTransition $transition, ProjectState $from, ProjectState $to, ?int $timestamp = NULL): void {
    $this->project()->get(static::LIFECYCLE_HISTORY_FIELD)->appendItem([
      'transition' => $transition->value,
      'from' => $from->value,
      'to' => $to->value,
      'uid' => $this->currentUser->id(),
      'timestamp' => $timestamp ?? $this->time->getCurrentTime(),
    ]);
  }

}
