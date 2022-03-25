<?php

namespace Drupal\projects\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectWorkflowManager;

/**
 * Implements lifecycle workflow functionality for Project entities.
 */
class Project extends Node implements ProjectInterface {

  /**
   * The workflow manager for this project.
   *
   * @var \Drupal\projects\ProjectWorkflowManager $workflow
   */
  private ProjectWorkflowManager $workflowManager;

  /**
   * Call the workflow manager holding and manipulating the state of the
   * project.
   */
  public function workflowManager() {
    if (!isset($this->workflowManager)) {
      $this->workflowManager = new ProjectWorkflowManager($this, $this->entityTypeManager());
    }
    return $this->workflowManager;
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
          'name' => $applicant->get('field_name')->value,
        ];
      }
      else {
        $options[$applicant->id()] = $applicant->get('field_name')->value;
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
          'name' => $participant->get('field_name')->value,
          'task' => $tasks[$delta]['value'],
        ];
      }
      else {
        $options[$participant->id()] = $participant->get('field_name')->value;
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
          'name' => $manager->get('field_name')->value,
        ];
      }
      else {
        $options[$manager->id()] = $manager->get('field_name')->value;
      }
    }
    return $options;
  }

  /**
   * Does the organization of the project have a manager?
   */
  public function hasManager() {
    return $this->getOwner()->hasManager();
  }

}
