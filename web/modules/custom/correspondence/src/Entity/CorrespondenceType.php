<?php

namespace Drupal\correspondence\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the correspondence type entity.
 */
#[ConfigEntityType(
  id: 'correspondence_type',
  label: new TranslatableMarkup('Correspondence type'),
  label_collection: new TranslatableMarkup('Correspondence types'),
  label_singular: new TranslatableMarkup('correspondence type'),
  label_plural: new TranslatableMarkup('correspondence types'),
  label_count: [
    'singular' => '@count correspondence type',
    'plural' => '@count correspondence types',
  ],
  config_prefix: 'correspondence_type',
  admin_permission: 'administer correspondence',
  bundle_of: 'correspondence',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  config_export: ['id', 'label'],
)]
class CorrespondenceType extends ConfigEntityBundleBase {

  /**
   * The correspondence type machine name.
   *
   * @var string
   */
  protected string $id;

  /**
   * The correspondence type label.
   *
   * @var string
   */
  protected string $label;

}
