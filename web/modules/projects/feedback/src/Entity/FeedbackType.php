<?php

namespace Drupal\feedback\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Feedback type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "feedback_type",
 *   label = @Translation("Feedback type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\feedback\Form\FeedbackTypeForm",
 *       "edit" = "Drupal\feedback\Form\FeedbackTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\feedback\FeedbackTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer feedback types",
 *   bundle_of = "feedback",
 *   config_prefix = "feedback_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/feedback_types/add",
 *     "edit-form" = "/admin/structure/feedback_types/manage/{feedback_type}",
 *     "delete-form" = "/admin/structure/feedback_types/manage/{feedback_type}/delete",
 *     "collection" = "/admin/structure/feedback_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class FeedbackType extends ConfigEntityBundleBase {

  /**
   * The machine name of this feedback type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the feedback type.
   *
   * @var string
   */
  protected $label;

}
