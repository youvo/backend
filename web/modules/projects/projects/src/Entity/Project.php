<?php

namespace Drupal\projects\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectWorkflowManager;
use Drupal\user\UserInterface;

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
   * {@inheritdoc}
   */
  public function getApplicants() {
    $applicants = [];
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $applicants_field */
    $applicants_field = $this->get('field_applicants');
    foreach ($applicants_field->referencedEntities() as $applicant) {
      $applicants[$applicant->id()] = $applicant;
    }
    return $applicants;
  }

  /**
   * {@inheritdoc}
   */
  public function setApplicants(array $applicants) {
    $this->set('field_applicants', NULL);
    foreach ($applicants as $applicant) {
      $this->get('field_applicants')
        ->appendItem(['target_id' => $applicant->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function appendApplicant(AccountInterface $applicant) {
    $this->get('field_applicants')
      ->appendItem(['target_id' => $applicant->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicant(AccountInterface $account) {
    return array_key_exists($account->id(), $this->getApplicants());
  }

  /**
   * {@inheritdoc}
   */
  public function hasApplicant() {
    return !empty($this->getApplicants());
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
