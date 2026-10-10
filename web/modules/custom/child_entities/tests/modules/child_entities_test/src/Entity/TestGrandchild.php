<?php

namespace Drupal\child_entities_test\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines a child entity of the test entity chain.
 *
 * @ContentEntityType(
 *   id = "child_test_grandchild",
 *   label = @Translation("TestGrandchild"),
 *   base_table = "child_test_grandchild",
 *   cascade_delete = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *     "parent" = "child_test_child",
 *     "weight" = "weight"
 *   }
 * )
 */
class TestGrandchild extends ContentEntityBase implements ChildEntityInterface {

  use ChildEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::childBaseFieldDefinitions($entity_type);
    $fields['name'] = BaseFieldDefinition::create('string')->setLabel('Name');
    return $fields;
  }

}
