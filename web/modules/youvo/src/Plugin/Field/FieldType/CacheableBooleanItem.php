<?php

namespace Drupal\youvo\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'boolean' entity field type with cacheability metadata.
 *
 * @FieldType(
 *   id = "cacheable_boolean",
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

  /**
   * Gets the value property.
   *
   * @returns \Drupal\youvo\Plugin\DataType\CacheableBooleanData
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getValueProperty() {
    /** @var \Drupal\youvo\Plugin\DataType\CacheableBooleanData $value */
    $value = $this->get('value');
    return $value;
  }

}
