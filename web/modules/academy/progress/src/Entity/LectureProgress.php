<?php

namespace Drupal\progress\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\progress\LectureProgressInterface;

/**
 * Defines the LectureProgress entity class.
 *
 * @ContentEntityType(
 *   id = "lecture_progress",
 *   label = @Translation("LectureProgress"),
 *   label_collection = @Translation("LectureProgress"),
 *   base_table = "lecture_progress",
 *   admin_permission = "administer progress",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "lecture" = "lecture",
 *     "uid" = "uid"
 *   }
 * )
 */
class LectureProgress extends ContentEntityBase implements LectureProgressInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getEnrollmentTime() {
    return $this->get('enrolled')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTime() {
    return $this->get('accessed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessTime(int $timestamp): LectureProgress {
    $this->set('accessed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompletedTime() {
    return $this->get('completed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCompletedTime(int $timestamp): LectureProgress {
    $this->set('completed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['lecture'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Lecture'))
      ->setDescription(t('The lecture ID.'))
      ->setSetting('target_type', 'lecture')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the question author.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The lecture language code on enrollment.'))
      ->setRequired(TRUE);

    $fields['enrolled'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Initial access (enrollment)'))
      ->setDescription(t('The time that the lecture was accessed initially.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['accessed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last accessed'))
      ->setDescription(t('The time that the lecture was last accessed.'))
      ->setRequired(TRUE);

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDescription(t('The time that the lecture was completed.'))
      ->setDefaultValue(0);

    return $fields;
  }

}
