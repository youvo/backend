<?php

namespace Drupal\logbook\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the log text entity class.
 *
 * This entity is used to avoid the locale and config_translation module.
 *
 * @ContentEntityType(
 *   id = "log_text",
 *   label = @Translation("Log Text"),
 *   base_table = "log_text",
 *   data_table = "log_text_field_data",
 *   admin_permission = "administer log pattern",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "log_pattern" = "log_pattern",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/log-pattern/{log_pattern}/text/{log_text}"
 *   }
 * )
 */
class LogText extends ContentEntityBase {

  /**
   * Gets text.
   */
  public function getText() {
    return $this->get('text')->value ?? '';
  }

  /**
   * Gets public text.
   */
  public function getPublicText() {
    return $this->get('public_text')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['text'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Text'))
      ->setDescription(new TranslatableMarkup('The text.'));

    $fields['public_text'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Public Text'))
      ->setDescription(new TranslatableMarkup('The public text.'));

    $fields['log_pattern'] = BaseFieldDefinition::create('string')
      ->setLabel('Log Pattern Machine Name')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    return $fields;
  }

}
