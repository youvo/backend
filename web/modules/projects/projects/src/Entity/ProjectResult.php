<?php

namespace Drupal\projects\Entity;

use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\projects\ProjectResultInterface;

/**
 * Defines the project result entity class.
 *
 * @ContentEntityType(
 *   id = "project_result",
 *   label = @Translation("Project Result"),
 *   label_collection = @Translation("Project Results"),
 *   handlers = {
 *     "access" = "Drupal\child_entities\ChildEntityAccessControlHandler",
 *     "form" = {
 *       "edit" = "Drupal\projects\Form\ProjectResultForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "project_result",
 *   fieldable = TRUE,
 *   admin_permission = "administer project result",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *     "parent" = "project",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "edit-form" = "/projects/{project}/result/{project_result}/edit",
 *   }
 * )
 */
class ProjectResult extends ContentEntityBase implements ProjectResultInterface {

  use ChildEntityTrait;
  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): ProjectResultInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFiles(array $file_targets): ProjectResultInterface {
    $this->set('field_files', NULL);
    foreach ($file_targets as $file_target) {
      $this->get('field_files')->appendItem($file_target);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLinks(array $links): ProjectResultInterface {
    $this->set('field_hyperlinks', NULL);
    foreach ($links as $link) {
      $this->get('field_hyperlinks')->appendItem($link);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setDescription(t('A boolean indicating whether the project result is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the project result was created.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the project result was last edited.'));

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
