<?php

namespace Drupal\child_entities_test\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a child entity of the test entity chain.
 */
#[ContentEntityType(
  id: 'child_test_grandchild',
  label: new TranslatableMarkup('TestGrandchild'),
  handlers: [
    'access' => 'Drupal\child_entities\ChildEntityAccessControlHandler',
  ],
  admin_permission: 'administer child test',
  base_table: 'child_test_grandchild',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
    'parent' => 'child_test_child',
    'weight' => 'weight',
  ],
  additional: [
    'cascade_delete' => TRUE,
  ],
)]
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
