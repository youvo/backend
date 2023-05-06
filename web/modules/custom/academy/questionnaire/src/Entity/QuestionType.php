<?php

namespace Drupal\questionnaire\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Question type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "question_type",
 *   label = @Translation("Question type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\questionnaire\Form\QuestionTypeForm",
 *       "edit" = "Drupal\questionnaire\Form\QuestionTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\questionnaire\QuestionTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer courses",
 *   bundle_of = "question",
 *   config_prefix = "type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/question_types/add",
 *     "edit-form" = "/admin/structure/question_types/manage/{question_type}",
 *     "delete-form" = "/admin/structure/question_types/manage/{question_type}/delete",
 *     "collection" = "/admin/structure/question_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class QuestionType extends ConfigEntityBundleBase {

  /**
   * The machine name of this question type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the question type.
   *
   * @var string
   */
  protected $label;

}
