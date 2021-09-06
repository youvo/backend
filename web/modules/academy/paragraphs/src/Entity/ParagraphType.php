<?php

namespace Drupal\paragraphs\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Paragraph type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "paragraph_type",
 *   label = @Translation("Paragraph type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\academy_paragraph\Form\ParagraphTypeForm",
 *       "edit" = "Drupal\academy_paragraph\Form\ParagraphTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\academy_paragraph\ParagraphTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer paragraph types",
 *   bundle_of = "paragraph",
 *   config_prefix = "paragraph_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/paragraph_types/add",
 *     "edit-form" = "/admin/structure/paragraph_types/manage/{paragraph_type}",
 *     "delete-form" = "/admin/structure/paragraph_types/manage/{paragraph_type}/delete",
 *     "collection" = "/admin/structure/paragraph_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class ParagraphType extends ConfigEntityBundleBase {

  /**
   * The machine name of this paragraph type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the paragraph type.
   *
   * @var string
   */
  protected $label;

}
