<?php

namespace Drupal\questionnaire\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the question submission entity class.
 *
 * @ContentEntityType(
 *   id = "question_submission",
 *   label = @Translation("Question Submission"),
 *   label_collection = @Translation("Question Submissions"),
 *   base_table = "question_submissions",
 *   admin_permission = "administer courses",
 *   entity_keys = {
 *     "id" = "sid",
 *     "uuid" = "uuid",
 *     "question" = "question",
 *     "uid" = "uid"
 *   }
 * )
 */
class QuestionSubmission extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new question_submission entity is created, set the uid entity
   * reference to the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    if (!isset($values['uid'])) {
      $values['uid'] = \Drupal::currentUser()->id();
    }
  }

  /**
   * Get created time.
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * Set created time.
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Get owner.
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * Get owner ID.
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * Set ownder ID.
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * Set owner.
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['question'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Question'))
      ->setDescription(t('The question ID of the respective submission.'))
      ->setSetting('target_type', 'question')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['question_revision'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Question revision ID'))
      ->setReadOnly(TRUE)
      ->setDefaultValue(0)
      ->setSetting('unsigned', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the question author.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['value'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Submission value'))
      ->setDescription(t('The value of the submission.'));

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setRequired(TRUE)
      ->setDescription(t('The submission language code.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the submission was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the submission was last edited.'));

    return $fields;
  }

}
