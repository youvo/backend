<?php

namespace Drupal\progress\Entity;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Provides a trait for progress entities.
 */
trait ProgressEntityTrait {

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
  public function setAccessTime(int $timestamp) {
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
  public function setCompletedTime(int $timestamp) {
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
   * Returns an array of base field definitions for progress entities.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   An array of base field definitions.
   */
  public static function progressBaseFieldDefinitions() {

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
