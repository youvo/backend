<?php

namespace Drupal\projects\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectWorkflowManager;

/**
 * Implements bundle class for Project entities.
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
   * {@inheritdoc}
   */
  public function isAuthor(AccountInterface|int $account) {
    return $this->getUid($account) == $this->getOwner()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthorOrManager(AccountInterface|int $account) {
    $organization = $this->getOwner();
    return $this->isAuthor($account) || ($this->isOrganization($organization) &&
        $organization->isManager($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getManager() {
    $organization = $this->getOwner();
    return $this->isOrganization($organization) ?
      $organization->getManager() : NULL;
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

  /**
   * Helper to ensure that owner is an organization
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account in question.
   *
   * @return bool
   *   Is organization?
   */
  private function isOrganization(AccountInterface $account) {
    return class_exists('Drupal\\organizations\\Entity\\Organization') &&
      $account instanceof \Drupal\organizations\Entity\Organization;
  }

}
