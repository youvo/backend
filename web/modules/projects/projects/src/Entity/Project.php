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
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $applicants_field */
    $applicants_field = $this->get('field_applicants');
    foreach ($applicants_field->referencedEntities() as $applicant) {
      $applicants[$applicant->id()] = $applicant;
    }
    return $applicants ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setApplicants(array $applicants) {
    $this->set('field_applicants', NULL);
    foreach ($applicants as $applicant) {
      $this->get('field_applicants')
        ->appendItem(['target_id' => $this->getUid($applicant)]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function appendApplicant(AccountInterface|int $applicant) {
    $uid = $applicant instanceof AccountInterface ?
      $applicant->id() : $applicant;
    $this->get('field_applicants')->appendItem(['target_id' => $uid]);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicant(AccountInterface|int $applicant) {
    return array_key_exists($this->getUid($applicant), $this->getApplicants());
  }

  /**
   * {@inheritdoc}
   */
  public function hasApplicant() {
    return !empty($this->getApplicants());
  }

  /**
   * {@inheritdoc}
   */
  public function getParticipants() {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $participants_field */
    $participants_field = $this->get('field_participants');
    $tasks = $this->get('field_participants_tasks')->getValue();
    foreach ($participants_field->referencedEntities() as $delta => $participant) {
        $participant->task = $tasks[$delta]['value'];
        $participants[$participant->id()] = $participant;
    }
    return $participants ?? [];
  }

  /**
   * Set participants for current project.
   */
  public function setParticipants(array $participants, array $tasks = []) {
    $this->set('field_participants', NULL);
    $this->set('field_participants_tasks', NULL);
    foreach ($participants as $delta => $participant) {
      $this->get('field_participants')
        ->appendItem(['target_id' => $this->getUid($participant)]);
      $task = $tasks[$delta] ?? 'Creative';
      $this->get('field_participants_tasks')->appendItem($task);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function appendParticipant(AccountInterface|int $participant, string $task = 'Creative') {
    $this->get('field_participants')
      ->appendItem(['target_id' => $this->getUid($participant)]);
    $this->get('field_participants_tasks')->appendItem($task);
  }

  /**
   * {@inheritdoc}
   */
  public function isParticipant(AccountInterface|int $participant) {
    return array_key_exists($this->getUid($participant), $this->getParticipants());
  }

  /**
   * {@inheritdoc}
   */
  public function hasParticipant() {
    return !empty($this->getParticipants());
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

  /**
   * Helper to get uid of an account.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The account or the uid.
   * @return \Drupal\Core\Session\AccountInterface|int
   *   The uid.
   */
  private function getUid(AccountInterface|int $account) {
    return $account instanceof AccountInterface ? $account->id() : $account;
  }

}
