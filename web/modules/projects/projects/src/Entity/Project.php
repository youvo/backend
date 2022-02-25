<?php

namespace Drupal\projects\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\projects\ProjectInterface;

/**
 * Implements lifecycle workflow functionality for Project entities.
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
    return $this->transition(self::TRANSITION_SUBMIT, self::STATE_PENDING);
  }

  /**
   * Publish project.
   */
  public function transitionPublish() {
    return $this->transition(self::TRANSITION_PUBLISH, self::STATE_OPEN);
  }

  /**
   * Mediate project.
   */
  public function transitionMediate() {
    return $this->transition(self::TRANSITION_MEDIATE, self::STATE_ONGOING);
  }

  /**
   * Complete project.
   */
  public function transitionComplete() {
    return $this->transition(self::TRANSITION_COMPLETE, self::STATE_COMPLETED);
  }

  /**
   * Reset project.
   */
  public function transitionReset() {
    return $this->transition(self::TRANSITION_RESET, self::STATE_DRAFT);
  }

  /**
   * Get applicants for current project.
   */
  public function getApplicantsAsArray(bool $populated = FALSE) {
    $options = [];
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $applicants */
    $applicants = $this->get('field_applicants');
    /** @var \Drupal\user\Entity\User $applicant */
    foreach ($applicants->referencedEntities() as $applicant) {
      if ($populated) {
        $options[$applicant->id()] = [
          'type' => 'user',
          'id' => $applicant->uuid(),
          'name' => $applicant->get('fullname')->value,
        ];
      }
      else {
        $options[$applicant->id()] = $applicant->get('fullname')->value;
      }

    }
    return $options;
  }

  /**
   * Set applicants to project.
   */
  public function setApplicants(array $applicants) {
    $this->set('field_applicants', NULL);
    foreach ($applicants as $uid) {
      $this->get('field_applicants')->appendItem(['target_id' => $uid]);
    }
    try {
      $this->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('Projects: Could not set applicants.', $e);
    }
  }

  /**
   * Append applicant by uid to project.
   */
  public function appendApplicant(int $applicant_uid) {
    $this->get('field_applicants')->appendItem(['target_id' => $applicant_uid]);
    try {
      $this->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('Projects: Could not append applicant.', $e);
    }
  }

  /**
   * Get participants for current project.
   */
  public function getParticipantsAsArray(bool $populated = FALSE) {
    $options = [];
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $participants */
    $participants = $this->get('field_participants');
    $tasks = $this->get('field_participants_tasks')->getValue();
    /** @var \Drupal\user\Entity\User $participant */
    foreach ($participants->referencedEntities() as $delta => $participant) {
      if ($populated) {
        $options[$participant->id()] = [
          'type' => 'user',
          'id' => $participant->uuid(),
          'name' => $participant->get('fullname')->value,
          'task' => $tasks[$delta]['value'],
        ];
      }
      else {
        $options[$participant->id()] = $participant->get('fullname')->value;
      }
    }
    return $options;
  }

  /**
   * Set participants for current project.
   */
  public function setParticipants(array $participants, array $tasks = []) {
    $this->set('field_participants', NULL);
    $this->set('field_participants_tasks', NULL);
    foreach ($participants as $delta => $participant_uid) {
      $this->get('field_participants')->appendItem(['target_id' => $participant_uid]);
      $task = $tasks[$delta] ?? 'Creative';
      $this->get('field_participants_tasks')->appendItem($task);
    }
    try {
      $this->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('Projects: Could not set participants.', $e);
    }
  }

  /**
   * Append  participant by uid to project.
   */
  public function appendParticipant(int $participant_uid, string $task = 'Creative') {
    $this->get('field_participants')->appendItem(['target_id' => $participant_uid]);
    $this->get('field_participants_tasks')->appendItem($task);
    try {
      $this->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('Projects: Could not append participant.', $e);
    }
  }

  /**
   * Get manager(s) for organization of the project.
   *
   * We expect that only one person manages a project but allow multiple
   * managers for future workflow adjustments.
   */
  public function getManagersAsArray(bool $populated = FALSE) {
    $options = [];
    $organization = $this->getOwner();
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $managers */
    $managers = $organization->get('field_manager');
    /** @var \Drupal\user\Entity\User $manager */
    foreach ($managers->referencedEntities() as $manager) {
      if ($populated) {
        $options[$manager->id()][] = [
          'type' => 'user',
          'id' => $manager->uuid(),
          'name' => $manager->get('fullname')->value,
        ];
      }
      else {
        $options[$manager->id()] = $manager->get('fullname')->value;
      }
    }
    return $options;
  }

  /**
   * Does the organization of the project have a manager?
   *
   * We expect that only one person manages a project but allow multiple
   * managers for future workflow adjustments.
   */
  public function hasManager() {
    $count = 0;
    $organization = $this->getOwner();
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $managers */
    $managers = $organization->get('field_manager');
    /** @var \Drupal\user\Entity\User $manager */
    foreach ($managers->referencedEntities() as $manager) {
      if (in_array('manager', $manager->getRoles())) {
        $count = $count + 1;
      }
    }
    return (bool) $count;
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
    return !empty($this->getApplicantsAsArray()) &&
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
      $this->set('field_lifecycle', $new_state);
      try {
        $this->save();
        return TRUE;
      }
      catch (EntityStorageException $e) {
        watchdog_exception('Projects: Could not perform transition.', $e);
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
      watchdog_exception('Projects: Could not load workflow.', $e);
    }
    return $workflow ?? NULL;
  }

}
