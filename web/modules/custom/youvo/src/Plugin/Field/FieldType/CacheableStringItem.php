<?php

namespace Drupal\youvo\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\youvo\Plugin\DataType\CacheableStringData;

/**
 * Defines the 'cacheable string' entity field type.
 *
 * @FieldType(
 *   id = "cacheable_string",
 *   label = @Translation("Cacheable Text (plain)"),
 *   description = @Translation("A field containing a plain cacheable string value."),
 *   no_ui = TRUE,
 *   default_widget = "string_textfield",
 *   default_formatter = "string"
 * )
 */
class CacheableStringItem extends StringItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('cacheable_string')
      ->setLabel(new TranslatableMarkup('Cacheable Text value'))
      ->setSetting('case_sensitive', $field_definition->getSetting('case_sensitive'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * Explicitly allow empty strings (""), to be able to attach caching info.
   *
   * @see \Drupal\questionnaire\Plugin\Field\SubmissionFieldItemList
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return $value === NULL;
  }

  /**
   * Gets the value property.
   *
   * @returns \Drupal\youvo\Plugin\DataType\CacheableStringData
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getValueProperty(): CacheableStringData {
    /** @var \Drupal\youvo\Plugin\DataType\CacheableStringData $value */
    $value = $this->get('value');
    return $value;
  }

}
