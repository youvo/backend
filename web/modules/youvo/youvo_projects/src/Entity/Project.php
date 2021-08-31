<?php

namespace Drupal\youvo_projects\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
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
  public function canTransitionSubmit() {
    return $this->hasTransition($this->getState(), self::STATE_PENDING);
  }

  /**
   * Checks if project can transition to state 'open'.
   */
  public function canTransitionPublish() {
    return $this->hasTransition($this->getState(), self::STATE_OPEN);
  }

  /**
   * Checks if project can transition to state 'ongoing'.
   */
  public function canTransitionMediate() {
    return !empty($this->getApplicantsAsArray()) &&
      $this->hasTransition($this->getState(), self::STATE_ONGOING);
  }

  /**
   * Checks if project can transition to state 'completed'.
   */
  public function canTransitionComplete() {
    return $this->hasTransition($this->getState(), self::STATE_COMPLETED);
  }

  /**
   * Checks if project can transition to state 'draft'.
   */
  public function canTransitionReset() {
    return $this->hasTransition($this->getState(), self::STATE_DRAFT);
  }

  /**
   * Checks if project can transition.
   */
  public function canTransitionByName($transition) {
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
    return $this->transition(self::STATE_PENDING);
  }

  /**
   * Publish project.
   */
  public function transitionPublish() {
    return $this->transition(self::STATE_OPEN);
  }

  /**
   * Mediate project.
   */
  public function transitionMediate() {
    return $this->transition(self::STATE_ONGOING);
  }

  /**
   * Complete project.
   */
  public function transitionComplete() {
    return $this->transition(self::STATE_COMPLETED);
  }

  /**
   * Reset project.
   */
  public function transitionReset() {
    return $this->transition(self::STATE_DRAFT);
  }

  /**
   * Abstraction of forward transition flow check.
   */
  private function hasTransition($current_state, $new_state) {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->loadWorkflowForProject();
    return $current_state != $new_state &&
      $workflow->getTypePlugin()->hasTransitionFromStateToState($current_state, $new_state);
  }

  /**
   * Set new lifecycle for transition.
   */
  private function transition($new_state) {
    if ($this->canTransitionByName($new_state)) {
      $this->set('field_lifecycle', $new_state);
      try {
        $this->save();
        return TRUE;
      }
      catch (EntityStorageException $e) {
        watchdog_exception('Youvo Projects', $e);
      }
    }
    return FALSE;
  }

  /**
   * Loads workflow for current project.
   */
  private function loadWorkflowForProject() {
    try {
      $workflow = $this->entityTypeManager()
        ->getStorage('workflow')
        ->load('project_lifecycle');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      watchdog_exception('Youvo Projects', $e);
    }
    return $workflow ?? NULL;
  }

  /**
   * Get applicants for current project.
   */
  public function getApplicantsAsArray(bool $use_uuid = FALSE) {
    $options = [];
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $applicants */
    $applicants = $this->get('field_applicants');
    foreach ($applicants->referencedEntities() as $applicant) {
      /** @var \Drupal\user\Entity\User $applicant */
      $id = $use_uuid ? $applicant->uuid() : $applicant->id();
      $options[$id] = $applicant->get('field_name')->value;
    }
    return $options;
  }

  /**
   * Set applicants for current project.
   */
  public function setApplicants(array $applicants) {
    foreach ($applicants as $applicant) {
      $this->get('field_applicants')->appendItem($applicant);
    }
    try {
      $this->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('Youvo Projects', $e);
    }
  }

  /**
   * Get participants for current project.
   */
  public function getParticipantsAsArray(bool $use_uuid = FALSE) {
    $options = [];
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $participants */
    $participants = $this->get('field_participants');
    foreach ($participants->referencedEntities() as $participant) {
      /** @var \Drupal\user\Entity\User $participant */
      $id = $use_uuid ? $participant->uuid() : $participant->id();
      $options[$id] = $participant->get('field_name')->value;
    }
    return $options;
  }

  /**
   * Set participants for current project.
   */
  public function setParticipants(array $participants, bool $reset = FALSE) {
    if ($reset) {
      $this->set('field_participants', NULL);
    }
    foreach ($participants as $participantId) {
      $this->get('field_participants')->appendItem($participantId);
    }
    try {
      $this->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('Youvo Projects', $e);
    }
  }

}
