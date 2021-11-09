<?php

namespace Drupal\progress\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\progress\ProgressInterface;

/**
 * Defines the CourseProgress entity class.
 *
 * @ContentEntityType(
 *   id = "course_progress",
 *   label = @Translation("Course Progress"),
 *   label_collection = @Translation("Course Progress"),
 *   base_table = "course_progress",
 *   admin_permission = "administer progress",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "course" = "course",
 *     "uid" = "uid"
 *   }
 * )
 */
class CourseProgress extends ContentEntityBase implements ProgressInterface {

  use EntityChangedTrait;
  use ProgressEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::progressBaseFieldDefinitions();

    $fields['course'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'))
      ->setDescription(t('The course ID.'))
      ->setSetting('target_type', 'course')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    return $fields;
  }

}
