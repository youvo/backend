<?php

namespace Drupal\correspondence\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the correspondence type entity.
 *
 * @ConfigEntityType(
 *   id = "correspondence_type",
 *   label = @Translation("Correspondence type"),
 *   label_collection = @Translation("Correspondence types"),
 *   label_singular = @Translation("correspondence type"),
 *   label_plural = @Translation("correspondence types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count correspondence type",
 *     plural = "@count correspondence types",
 *   ),
 *   config_prefix = "correspondence_type",
 *   admin_permission = "administer correspondence",
 *   bundle_of = "correspondence",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label"
 *   }
 * )
 */
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
