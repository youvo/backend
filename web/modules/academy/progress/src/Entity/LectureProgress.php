<?php

namespace Drupal\progress\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

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
class LectureProgress extends Progress {

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

    return $fields;
  }

}
