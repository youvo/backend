<?php

namespace Drupal\logbook\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\logbook\LogTextInterface;

/**
 * Defines the log text entity class.
 *
 * This entity is used to avoid the locale and config_translation module.
 *
 * @ContentEntityType(
 *   id = "log_text",
 *   label = @Translation("Log Text"),
 *   label_collection = @Translation("Log Texts"),
 *   label_singular = @Translation("Log Text"),
 *   handlers = {
 *     "access" = "Drupal\child_entities\ChildEntityAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\logbook\Form\LogTextForm",
 *       "edit" = "Drupal\logbook\Form\LogTextForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "log_text",
 *   data_table = "log_text_field_data",
 *   admin_permission = "administer log pattern",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "parent" = "log_pattern",
 *     "weight" = "weight",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/log-pattern/{log_pattern}/text/{log_text}"
 *   }
 * )
 */
class LogText extends ContentEntityBase implements LogTextInterface {

  use ChildEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function getText() {
    return $this->get('text')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicText() {
    return $this->get('public_text')->value ?? '';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
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

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
