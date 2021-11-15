<?php

namespace Drupal\academy\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\options\Plugin\Field\FieldType\ListStringItem;

/**
 * Plugin implementation of the 'list_cacheable_string' field type.
 *
 * @FieldType(
 *   id = "list_cacheable_string",
 *   label = @Translation("List (cacheable string)"),
 *   description = @Translation("This field stores cacheable string values from a list of allowed 'value => label' pairs, i.e. 'US States': IL => Illinois, IA => Iowa, IN => Indiana."),
 *   no_ui = TRUE,
 *   default_widget = "options_select",
 *   default_formatter = "list_default",
 * )
 */
class ListCacheableStringItem extends ListStringItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('cacheable_string')
      ->setLabel(t('String value'))
      ->addConstraint('Length', ['max' => 255])
      ->setRequired(TRUE);

    return $properties;
  }

}
