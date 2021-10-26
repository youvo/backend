<?php

namespace Drupal\progress\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'boolean' entity field type with cacheability metadata.
 *
 * @FieldType(
 *   id = "cacheable_boolean_item",
 *   label = @Translation("Cacheable Boolean Item"),
 *   description = @Translation("A field containing a boolean value and cacheability metadata."),
 *   no_ui = TRUE,
 *   default_widget = "boolean_checkbox",
 *   default_formatter = "boolean"
 * )
 */
class CacheableBooleanItem extends BooleanItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('cacheable_boolean')
      ->setLabel(t('Cacheable boolean value'))
      ->setRequired(TRUE);
    return $properties;
  }

}
