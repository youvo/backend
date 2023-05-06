<?php

namespace Drupal\youvo\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'weighted string' field type.
 *
 * @FieldType(
 *   id = "weighted_string",
 *   label = @Translation("Weighted Text (plain)"),
 *   description = @Translation("A field containing a plain weighted string value."),
 *   no_ui = TRUE,
 *   default_widget = "string_textfield",
 *   default_formatter = "string"
 * )
 */
class WeightedStringItem extends StringItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => $field_definition->getSetting('is_ascii') === TRUE ? 'varchar_ascii' : 'varchar',
          'length' => (int) $field_definition->getSetting('max_length'),
          'binary' => $field_definition->getSetting('case_sensitive'),
        ],
        'description' => [
          'description' => 'The description.',
          'type' => 'text',
        ],
        'weight' => [
          'description' => 'Weight of the string.',
          'type' => 'int',
          'default' => 0,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Text value'));

    $properties['description'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Description'));

    $properties['weight'] = DataDefinition::create('integer')
      ->setLabel(t('Weight'));

    return $properties;
  }

}
