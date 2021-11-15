<?php

namespace Drupal\academy\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'list_cacheable_integer' field type.
 *
 * @FieldType(
 *   id = "list_cacheable_integer",
 *   label = @Translation("List (cacheable integer)"),
 *   description = @Translation("This field stores integer values from a list of allowed 'value => label' pairs, i.e. 'Lifetime in days': 1 => 1 day, 7 => 1 week, 31 => 1 month."),
 *   no_ui = TRUE,
 *   default_widget = "options_select",
 *   default_formatter = "list_default",
 * )
 */
class ListCacheableIntegerItem extends ListIntegerItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('cacheable_integer')
      ->setLabel(t('Cacheable integer value'))
      ->setRequired(TRUE);

    return $properties;
  }

}
