<?php

namespace Drupal\projects;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides methods to manage the workflow of a project.
 */
class ProjectLifecycle {

  const STATE_DRAFT = 'draft';
  const STATE_PENDING = 'pending';
  const STATE_OPEN = 'open';
  const STATE_ONGOING = 'ongoing';
  const STATE_COMPLETED = 'completed';

  const TRANSITION_SUBMIT = 'submit';
  const TRANSITION_PUBLISH = 'publish';
  const TRANSITION_MEDIATE = 'mediate';
  const TRANSITION_COMPLETE = 'complete';
  const TRANSITION_RESET = 'reset';

  const WORKFLOW_ID = 'project_lifecycle';
  const LIFECYCLE_FIELD = 'field_lifecycle';

  /**
   * The project calling the lifecycle.
   *
   * @var \Drupal\projects\ProjectInterface|null
   */
  protected ?ProjectInterface $project = NULL;

  /**
   * The workflow storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $workflowStorage;

  /**
   * Constructs a ProjectLifecycle object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->workflowStorage = $entity_type_manager->getStorage('workflow');
  }

  /**
   * Sets the project property.
   */
  public function setProject(ProjectInterface $project): void {
    $this->project = $project;
  }

  /**
   * Calls the project property.
   */
  protected function project(): ProjectInterface {
    if (isset($this->project)) {
      return $this->project;
    }
    throw new \UnexpectedValueException('Project not set properly in workflow manager.');
  }

  /**
   * Gets the current state of the project.
   */
  protected function getState(): string {
    return $this->project()->get(self::LIFECYCLE_FIELD)->value;
  }

  /**
   * Checks if the project is a draft.
   */
  public function isDraft(): bool {
    return $this->getState() === self::STATE_DRAFT;
  }

  /**
   * Checks if the project is pending.
   */
  public function isPending(): bool {
    return $this->getState() === self::STATE_PENDING;
  }

  /**
   * Checks if the project is open.
   */
  public function isOpen(): bool {
    return $this->getState() === self::STATE_OPEN;
  }

  /**
   * Checks if the project is ongoing.
   */
  public function isOngoing(): bool {
    return $this->getState() === self::STATE_ONGOING;
  }

  /**
   * Checks if the project is completed.
   */
  public function isCompleted(): bool {
    return $this->getState() === self::STATE_COMPLETED;
  }

  /**
   * Checks if the project can transition by transition label.
   */
  public function canTransition(string $transition): bool {
    if ($transition == self::TRANSITION_MEDIATE) {
      return $this->project()->hasApplicant() &&
        $this->hasTransition($transition);
    }
    return $this->hasTransition($transition);
  }

  /**
   * Submits the project.
   */
  public function submit(): bool {
    return $this->doTransition(self::TRANSITION_SUBMIT);
  }

  /**
   * Publishes the project.
   */
  public function publish(): bool {
    return $this->doTransition(self::TRANSITION_PUBLISH);
  }

  /**
   * Mediates the project.
   */
  public function mediate(): bool {
    return $this->doTransition(self::TRANSITION_MEDIATE);
  }

  /**
   * Completes the project.
   */
  public function complete(): bool {
    return $this->doTransition(self::TRANSITION_COMPLETE);
  }

  /**
   * Resets the project.
   */
  public function reset(): bool {
    return $this->doTransition(self::TRANSITION_RESET);
  }

  /**
   * Abstraction of forward transition flow check.
   */
  protected function hasTransition($transition): bool {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->workflowStorage->load(self::WORKFLOW_ID);
    return $workflow->getTypePlugin()->hasTransition($transition);
  }

  /**
   * Sets new project state for given transition.
   */
  protected function doTransition(string $transition): bool {
    if ($this->canTransition($transition)) {
      $new_state = $this->getSuccessorFromTransition($transition);
      $this->project()->set(self::LIFECYCLE_FIELD, $new_state);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets new state assuming linear transition flow.
   */
  protected function getSuccessorFromTransition(string $transition): string {
    return match ($transition) {
      self::TRANSITION_SUBMIT => self::STATE_PENDING,
      self::TRANSITION_PUBLISH => self::STATE_OPEN,
      self::TRANSITION_MEDIATE => self::STATE_ONGOING,
      self::TRANSITION_COMPLETE => self::STATE_COMPLETED,
      self::TRANSITION_RESET => self::STATE_DRAFT,
      default => '',
    };
  }

}
