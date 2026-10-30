<?php

declare(strict_types=1);

namespace Drupal\lifecycle\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a lifecycle history field item.
 *
 * This field type does not provide an UI and is intended to be used in code.
 *
 * @property string|null $transition
 * @property string|null $from
 * @property string $to
 * @property int $uid
 * @property int $timestamp
 */
#[FieldType(
  id: 'lifecycle_history_item',
  label: new TranslatableMarkup('Lifecycle History'),
  description: new TranslatableMarkup('Stores the transitions of a workflow state.'),
  no_ui: TRUE,
)]
class LifecycleHistoryItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {

    $properties['transition'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Transition'))
      ->setDescription(new TranslatableMarkup('The type of the transition.'));

    $properties['from'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('From'))
      ->setDescription(new TranslatableMarkup('The state that the transition started from.'));

    $properties['to'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('To'))
      ->setDescription(new TranslatableMarkup('The state that the transition went to.'))
      ->setRequired(TRUE);

    $properties['uid'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Initiator'))
      ->setDescription(new TranslatableMarkup('The initiator of the transition.'))
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE);

    $properties['timestamp'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Timestamp'))
      ->setDescription(new TranslatableMarkup('The time of the transition.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'transition' => [
          'description' => 'The type of the transition.',
          'type' => 'varchar',
          'length' => 64,
        ],
        'from' => [
          'description' => 'The state that the transition started from.',
          'type' => 'varchar',
          'length' => 64,
        ],
        'to' => [
          'description' => 'The state that the transition went to.',
          'type' => 'varchar',
          'length' => 64,
        ],
        'uid' => [
          'description' => 'The initiator of the transition.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'timestamp' => [
          'description' => 'The time of the transition.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
      ],
      'indexes' => [
        'format' => ['transition'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName(): string {
    return 'to';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return $this->to === NULL || $this->to === '';
  }

}
