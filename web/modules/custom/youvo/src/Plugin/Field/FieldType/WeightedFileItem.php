<?php

namespace Drupal\youvo\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Plugin implementation of the 'weighted file' field type.
 *
 * @FieldType(
 *   id = "weighted_file",
 *   label = @Translation("Weighted File"),
 *   description = @Translation("This field stores the ID of a file as an integer value."),
 *   category = "reference",
 *   no_ui = TRUE,
 *   default_widget = "file_generic",
 *   default_formatter = "file_default",
 *   list_class = "\Drupal\file\Plugin\Field\FieldType\FileFieldItemList",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}}
 * )
 */
class WeightedFileItem extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['weight'] = [
      'description' => 'Weight of the file.',
      'type' => 'int',
      'default' => 0,
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['weight'] = DataDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The weight of the file.'));

    return $properties;
  }

}
