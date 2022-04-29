<?php

namespace Drupal\projects\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\organizations\ManagerInterface;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectLifecycle;
use Drupal\user_types\Utility\Profile;

/**
 * Implements bundle class for Project entities.
 */
class Project extends Node implements ProjectInterface {

  /**
   * The project lifecycle.
   *
   * @var \Drupal\projects\ProjectLifecycle
   */
  protected ProjectLifecycle $lifecycle;

  /**
   * Calls project workflow manager which holds/manipulates the state.
   */
  public function lifecycle() {
    if (!isset($this->lifecycle)) {
      $this->lifecycle = \Drupal::service('project.lifecycle');
      $this->lifecycle->setProject($this);
    }
    return $this->lifecycle;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {

    // Invalidate cache to recalculate the field projects of the organization.
    if (!$this->isNew()) {
      /** @var \Drupal\organizations\Entity\Organization $organization */
      $organization = $this->getOwner();
      Cache::invalidateTags($organization->getCacheTagsToInvalidate());
    }

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {

    // Invalidate cache to recalculate the field projects of the organization.
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $this->getOwner();
    Cache::invalidateTags($organization->getCacheTagsToInvalidate());

    parent::postCreate($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function getApplicants() {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $applicants_field */
    $applicants_field = $this->get('field_applicants');
    /** @var \Drupal\user\UserInterface $applicant */
    foreach ($applicants_field->referencedEntities() as $applicant) {
      $applicants[intval($applicant->id())] = $applicant;
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
        ->appendItem(['target_id' => Profile::id($applicant)]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function appendApplicant(AccountInterface|int $applicant) {
    $this->get('field_applicants')
      ->appendItem(['target_id' => Profile::id($applicant)]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicant(AccountInterface|int $applicant) {
    return array_key_exists(Profile::id($applicant), $this->getApplicants());
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
    /** @var \Drupal\user\UserInterface $participant */
    foreach ($participants_field->referencedEntities() as $delta => $participant) {
      $participant->task = $tasks[$delta]['value'];
      $participants[intval($participant->id())] = $participant;
    }
    return $participants ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setParticipants(array $participants, array $tasks = []) {
    $this->set('field_participants', NULL);
    $this->set('field_participants_tasks', NULL);
    foreach ($participants as $delta => $participant) {
      $this->get('field_participants')
        ->appendItem(['target_id' => Profile::id($participant)]);
      $task = $tasks[$delta] ?? 'Creative';
      $this->get('field_participants_tasks')->appendItem($task);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function appendParticipant(AccountInterface|int $participant, string $task = 'Creative') {
    $this->get('field_participants')
      ->appendItem(['target_id' => Profile::id($participant)]);
    $this->get('field_participants_tasks')->appendItem($task);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isParticipant(AccountInterface|int $participant) {
    return array_key_exists(Profile::id($participant), $this->getParticipants());
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
    return Profile::id($account) == $this->getOwner()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthorOrManager(AccountInterface|int $account) {
    $owner = $this->getOwner();
    return $this->isAuthor($account) ||
      ($owner instanceof ManagerInterface && $owner->isManager($account));
  }

  /**
   * {@inheritdoc}
   */
  public function isManager(AccountInterface|int $account) {
    $owner = $this->getOwner();
    return $owner instanceof ManagerInterface && $owner->isManager($account);
  }

  /**
   * {@inheritdoc}
   */
  public function getManager() {
    $owner = $this->getOwner();
    return $owner instanceof ManagerInterface ? $owner->getManager() : NULL;
  }

}
