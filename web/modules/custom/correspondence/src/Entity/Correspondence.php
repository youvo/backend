<?php

namespace Drupal\correspondence\Entity;

use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\correspondence\CorrespondenceInterface;

/**
 * Defines the correspondence entity.
 */
#[ContentEntityType(
  id: 'correspondence',
  label: new TranslatableMarkup('Correspondence'),
  label_collection: new TranslatableMarkup('Correspondence'),
  bundle_label: new TranslatableMarkup('Correspondence type'),
  handlers: [
    'access' => 'Drupal\correspondence\CorrespondenceAccessControlHandler',
  ],
  base_table: 'correspondence',
  admin_permission: 'administer correspondence',
  translatable: FALSE,
  entity_keys: [
    'id' => 'id',
    'bundle' => 'type',
    'label' => 'subject',
    'uuid' => 'uuid',
    'parent' => 'project',
    'weight' => 'weight',
  ],
  bundle_entity_type: 'correspondence_type',
  additional: [
    'cascade_delete' => TRUE,
  ],
)]
class Correspondence extends ContentEntityBase implements CorrespondenceInterface {

  use ChildEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::childBaseFieldDefinitions($entity_type);

    $fields['sender'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sender'))
      ->setSetting('target_type', 'user');
    $fields['recipients'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Recipients'));
    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject'))
      ->setSetting('max_length', 255);
    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Body'));
    $fields['subtype'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Mail type'))
      ->setSetting('max_length', 128);
    $fields['origin'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Origin'))
      ->setSetting('allowed_values', ['automatic' => 'Automatic', 'manager' => 'Manager'])
      ->setDefaultValue('automatic');
    $fields['result'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Result'))
      ->setSetting('allowed_values', ['sent' => 'Sent', 'failed' => 'Failed', 'link' => 'Link only'])
      ->setDefaultValue('sent');
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));
    $fields['occurred'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Occurred'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtype(): string {
    return (string) $this->get('subtype')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isSent(): bool {
    return $this->get('result')->value === 'sent';
  }

  /**
   * {@inheritdoc}
   */
  public function isNote(): bool {
    return $this->bundle() === 'note';
  }

  /**
   * {@inheritdoc}
   */
  public function getOccurredTime(): int {
    return (int) ($this->get('occurred')->value ?: $this->get('created')->value);
  }

}
