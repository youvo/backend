<?php

namespace Drupal\child_entities_test\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the origin of the test entity chain, which is not a child itself.
 *
 * @ContentEntityType(
 *   id = "child_test_origin",
 *   label = @Translation("Test origin"),
 *   base_table = "child_test_origin",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name"
 *   }
 * )
 */
class TestOrigin extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['name'] = BaseFieldDefinition::create('string')->setLabel('Name');
    return $fields;
  }

}
