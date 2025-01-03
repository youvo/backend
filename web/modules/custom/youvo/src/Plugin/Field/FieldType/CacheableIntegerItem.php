<?php

namespace Drupal\youvo\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\youvo\Plugin\DataType\CacheableIntegerData;

/**
 * Defines the 'integer' entity field type with cacheability metadata.
 *
 * @FieldType(
 *   id = "cacheable_integer",
 *   label = @Translation("Cacheable Integer Item"),
 *   description = @Translation("A field containing a integer value and cacheability metadata."),
 *   no_ui = TRUE,
 *   default_widget = "number",
 *   default_formatter = "number_integer"
 * )
 */
class CacheableIntegerItem extends IntegerItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('cacheable_integer')
      ->setLabel(t('Cacheable integer value'))
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * Gets the value property.
   *
   * @returns \Drupal\youvo\Plugin\DataType\CacheableIntegerData
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getValueProperty(): CacheableIntegerData {
    /** @var \Drupal\youvo\Plugin\DataType\CacheableIntegerData $value */
    $value = $this->get('value');
    return $value;
  }

}
