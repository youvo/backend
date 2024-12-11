<?php

namespace Drupal\youvo\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;

/**
 * Defines the 'entity reference' field type with cacheability metadata.
 *
 * @FieldType(
 *   id = "cacheable_entity_reference",
 *   label = @Translation("Cacheable Entity Reference Item"),
 *   description = @Translation("A field containing an entity reference value and cacheability metadata."),
 *   no_ui = TRUE,
 *   category = "reference",
 *   default_widget = "entity_reference_autocomplete",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList"
 * )
 */
class CacheableEntityReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $settings = $field_definition->getSettings();
    $target_type_info = \Drupal::entityTypeManager()->getDefinition($settings['target_type']);
    $properties['target_id'] = DataReferenceTargetDefinition::create('cacheable_integer')
      ->setLabel(new TranslatableMarkup('@label ID', ['@label' => $target_type_info->getLabel()]))
      ->setSetting('unsigned', TRUE);
    return $properties;
  }

  /**
   * Gets the target ID property.
   *
   * @returns \Drupal\youvo\Plugin\DataType\CacheableIntegerData
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getTargetIdProperty() {
    /** @var \Drupal\youvo\Plugin\DataType\CacheableIntegerData $target_id */
    $target_id = $this->get('target_id');
    return $target_id;
  }

}
