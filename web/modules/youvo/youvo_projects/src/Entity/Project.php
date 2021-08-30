<?php

namespace Drupal\youvo_projects\Entity;

use Drupal\node\Entity\Node;
use Drupal\youvo_projects\ProjectInterface;

/**
 *
 */
class Project extends Node implements ProjectInterface {

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
   * Gets current state of project.
   */
  public function getState() {
    return $this->get('field_lifecycle')->value;
  }

  /**
   * Checks if project can transition to state 'ongoing'.
   */
  public function canSubmit() {
    return $this->canTransition($this->getState(), self::STATE_PENDING);
  }

  /**
   * Checks if project can transition to state 'open'.
   */
  public function canPublish() {
    return $this->canTransition($this->getState(), self::STATE_OPEN);
  }

  /**
   * Checks if project can transition to state 'ongoing'.
   */
  public function canMediate() {
    return $this->canTransition($this->getState(), self::STATE_ONGOING);
  }

  /**
   * Checks if project can transition to state 'completed'.
   */
  public function canComplete() {
    return $this->canTransition($this->getState(), self::STATE_COMPLETED);
  }

  /**
   * Checks if project can transition to state 'draft'.
   */
  public function canReset() {
    return $this->canTransition($this->getState(), self::STATE_DRAFT);
  }

  /**
   * Checks if project can transition.
   */
  public function canTransitionByName($transition) {
    switch ($transition) {
      case self::TRANSITION_SUBMIT:
        return $this->canSubmit();

      case self::TRANSITION_PUBLISH:
        return $this->canPublish();

      case self::TRANSITION_MEDIATE:
        return $this->canMediate();

      case self::TRANSITION_COMPLETE:
        return $this->canComplete();

      case self::TRANSITION_RESET:
        return $this->canReset();

      default:
        return FALSE;
    }
  }

  /**
   * Abstraction of forward transition flow.
   */
  private function canTransition($current_state, $new_state) {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->loadWorkflowForProject();
    return $current_state != $new_state &&
      $workflow->getTypePlugin()->hasTransitionFromStateToState($current_state, $new_state);
  }

  /**
   * Loads workflow for current project.
   */
  private function loadWorkflowForProject() {
    return $this->entityTypeManager()
      ->getStorage('workflow')
      ->load('project_lifecycle');
  }

  /**
   * Get applicants for current project.
   */
  public function getApplicants() {
    $options = [];
    foreach ($this->get('field_applicants')->referencedEntities() as $applicant) {
      $options[$applicant->id()] = $applicant->get('field_name')->value;
    }
    return $options;
  }

}
