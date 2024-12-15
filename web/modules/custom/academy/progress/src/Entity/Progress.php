<?php

namespace Drupal\progress\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\progress\ProgressInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Base class for progress entities.
 */
abstract class Progress extends ContentEntityBase implements ProgressInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getEnrollmentTime(): int {
    return (int) $this->get('enrolled')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTime(): int {
    return (int) $this->get('accessed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessTime(int $timestamp): static {
    $this->set('accessed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompletedTime(): int {
    return (int) $this->get('completed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCompletedTime(int $timestamp): static {
    $this->set('completed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields[$entity_type->getKey('owner')] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID associated with the progress.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The language code on enrollment.'))
      ->setRequired(TRUE);

    $fields['enrolled'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Initial access (enrollment)'))
      ->setDescription(t('The time that the entity was accessed initially.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['accessed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last accessed'))
      ->setDescription(t('The time that the entity was last accessed.'))
      ->setRequired(TRUE);

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDescription(t('The time that the entity was completed.'))
      ->setDefaultValue(0);

    return $fields;
  }

}
