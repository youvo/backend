<?php

namespace Drupal\projects;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Error;
use Drupal\projects\Entity\Project;

/**
 * Provides methods to manage the workflow of a project.
 */
class ProjectWorkflowManager {

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

  /**
   * The project calling the workflow manager.
   *
   * @param \Drupal\projects\Entity\Project $project
   */
  private Project $project;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * @param \Drupal\projects\Entity\Project $project
   *   The project.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    Project $project,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->project = $project;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets current state of project.
   */
  private function getState() {
    return $this->project->get('field_lifecycle')->value;
  }

  /**
   * Is the project a draft?
   */
  public function isDraft() {
    return $this->getState() === self::STATE_DRAFT;
  }

  /**
   * Is the project pending?
   */
  public function isPending() {
    return $this->getState() === self::STATE_PENDING;
  }

  /**
   * Is the project open?
   */
  public function isOpen() {
    return $this->getState() === self::STATE_OPEN;
  }

  /**
   * Is the project ongoing?
   */
  public function isOngoing() {
    return $this->getState() === self::STATE_ONGOING;
  }

  /**
   * Is the project completed?
   */
  public function isCompleted() {
    return $this->getState() === self::STATE_COMPLETED;
  }

  /**
   * Checks if project can transition.
   */
  public function canTransitionByLabel($transition) {
    return match ($transition) {
      self::TRANSITION_SUBMIT => $this->canTransitionSubmit(),
      self::TRANSITION_PUBLISH => $this->canTransitionPublish(),
      self::TRANSITION_MEDIATE => $this->canTransitionMediate(),
      self::TRANSITION_COMPLETE => $this->canTransitionComplete(),
      self::TRANSITION_RESET => $this->canTransitionReset(),
      default => FALSE,
    };
  }

  /**
   * Submit project.
   */
  public function transitionSubmit() {
    return $this->transition(self::TRANSITION_SUBMIT,self::STATE_PENDING);
  }

  /**
   * Publish project.
   */
  public function transitionPublish() {
    return $this->transition(self::TRANSITION_PUBLISH,self::STATE_OPEN);
  }

  /**
   * Mediate project.
   */
  public function transitionMediate() {
    return $this->transition(self::TRANSITION_MEDIATE,self::STATE_ONGOING);
  }

  /**
   * Complete project.
   */
  public function transitionComplete() {
    return $this->transition(self::TRANSITION_COMPLETE,self::STATE_COMPLETED);
  }

  /**
   * Reset project.
   */
  public function transitionReset() {
    return $this->transition(self::TRANSITION_RESET,self::STATE_DRAFT);
  }

  /**
   * Abstraction of forward transition flow check.
   */
  private function hasTransition($current_state, $new_state) {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->loadWorkflow();
    return $current_state != $new_state &&
      $workflow->getTypePlugin()
        ->hasTransitionFromStateToState($current_state, $new_state);
  }

  /**
   * Checks if project can transition to state 'ongoing'.
   */
  private function canTransitionSubmit() {
    return $this->hasTransition($this->getState(), self::STATE_PENDING);
  }

  /**
   * Checks if project can transition to state 'open'.
   */
  private function canTransitionPublish() {
    return $this->hasTransition($this->getState(), self::STATE_OPEN);
  }

  /**
   * Checks if project can transition to state 'ongoing'.
   */
  private function canTransitionMediate() {
    return !empty($this->project->getApplicantsAsArray()) &&
      $this->hasTransition($this->getState(), self::STATE_ONGOING);
  }

  /**
   * Checks if project can transition to state 'completed'.
   */
  private function canTransitionComplete() {
    return $this->hasTransition($this->getState(), self::STATE_COMPLETED);
  }

  /**
   * Checks if project can transition to state 'draft'.
   */
  private function canTransitionReset() {
    return $this->hasTransition($this->getState(), self::STATE_DRAFT);
  }

  /**
   * Set new lifecycle for transition.
   */
  private function transition($transition, $new_state) {
    if ($this->canTransitionByLabel($transition)) {
      $this->project->set('field_lifecycle', $new_state);
      try {
        $this->project->save();
        return TRUE;
      }
      catch (EntityStorageException $exception) {
        $variables = Error::decodeException($exception);
        \Drupal::logger('youvo')
          ->error('Projects: Could not perform transition. %type: @message in %function (line %line of %file).', $variables);
      }
    }
    return FALSE;
  }

  /**
   * Loads workflow for current project.
   */
  private function loadWorkflow() {
    try {
      $workflow = $this->entityTypeManager->getStorage('workflow')
        ->load('project_lifecycle');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $exception) {
      $variables = Error::decodeException($exception);
      \Drupal::logger('youvo')
        ->error('Projects: Could not load workflow. %type: @message in %function (line %line of %file).', $variables);
    }
    return $workflow ?? NULL;
  }

}
