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
 *       "add" = "Drupal\paragraphs\Form\ParagraphTypeForm",
 *       "edit" = "Drupal\paragraphs\Form\ParagraphTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\paragraphs\ParagraphTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer courses",
 *   bundle_of = "paragraph",
 *   config_prefix = "type",
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
   */
  protected string $id;

  /**
   * The human-readable name of the paragraph type.
   */
  protected string $label;

}
