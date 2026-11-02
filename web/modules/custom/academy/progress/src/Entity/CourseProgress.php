<?php

namespace Drupal\progress\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the CourseProgress entity class.
 */
#[ContentEntityType(
  id: 'course_progress',
  label: new TranslatableMarkup('Course Progress'),
  label_collection: new TranslatableMarkup('Course Progress'),
  base_table: 'course_progress',
  admin_permission: 'administer progress',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'course' => 'course',
    'owner' => 'uid',
  ],
)]
class CourseProgress extends Progress {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['course'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'))
      ->setDescription(t('The course ID.'))
      ->setSetting('target_type', 'course')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    return $fields;
  }

}
