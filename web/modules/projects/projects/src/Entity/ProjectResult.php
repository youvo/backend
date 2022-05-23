<?php

namespace Drupal\projects\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\projects\ProjectResultInterface;
use Drupal\user\EntityOwnerTrait;

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
 *       "add" = "Drupal\projects\Form\ProjectResultForm",
 *       "edit" = "Drupal\projects\Form\ProjectResultForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "project_result",
 *   admin_permission = "administer project result",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "published" = "published",
 *     "parent" = "node",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "add-form" = "/projects/{project}/result/add",
 *     "edit-form" = "/projects/{project}/result/{project_result}/edit",
 *     "delete-form" = "/projects/{project}/result/{project_result}/delete",
 *     "collection" = "/projects/{project}/result"
 *   },
 *   field_ui_base_route = "entity.project_result.settings"
 * )
 */
class ProjectResult extends ContentEntityBase implements ProjectResultInterface, ChildEntityInterface {

  use ChildEntityTrait;
  use EntityChangedTrait;
  use EntityOwnerTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   *
   * Sets the mandatory project value!
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    if (
      !isset($values['project']) &&
      $route_match = \Drupal::service('current_route_match')->getParameter('project')
    ) {
      $values['project'] = $route_match;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int|string $timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['published'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setDescription(t('A boolean indicating whether the project result is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the project result author.'))
      ->setSetting('target_type', 'user')
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
