<?php

namespace Drupal\lectures\Entity;

use Drupal\academy\AcademicFormatInterface;
use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the lecture entity class.
 *
 * @ContentEntityType(
 *   id = "lecture",
 *   label = @Translation("Lecture"),
 *   label_collection = @Translation("Academy"),
 *   label_singular = @Translation("Lecture"),
 *   handlers = {
 *     "access" = "Drupal\child_entities\ChildEntityAccessControlHandler",
 *     "list_builder" = "Drupal\lectures\LectureListBuilder",
 *     "form" = {
 *       "add" = "Drupal\lectures\Form\LectureForm",
 *       "edit" = "Drupal\lectures\Form\LectureForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "lectures",
 *   data_table = "lectures_field_data",
 *   translatable = TRUE,
 *   fieldable = FALSE,
 *   admin_permission = "administer courses",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "parent" = "course",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "add-form" = "/academy/co/{course}/le/add",
 *     "edit-form" = "/academy/co/{course}/le/{lecture}",
 *     "delete-form" = "/academy/co/{course}/le/{lecture}/delete",
 *     "collection" = "/academy"
 *   }
 * )
 */
class Lecture extends ContentEntityBase implements ChildEntityInterface, AcademicFormatInterface {

  use EntityChangedTrait;
  use ChildEntityTrait;

  /**
   * {@inheritdoc}
   *
   * When a new lecture entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   *
   * We set the mandatory course value here!
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    if (!isset($values['uid'])) {
      $values['uid'] = \Drupal::currentUser()->id();
    }
    if (!isset($values['course']) && $route_match = \Drupal::service('current_route_match')->getParameter('course')) {
      $values['course'] = $route_match;
    }
  }

  /**
   * Get title.
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * Set title.
   */
  public function setTitle(string $title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * Get status.
   */
  public function isEnabled() {
    return (bool) $this->get('status')->value;
  }

  /**
   * Set status.
   */
  public function setStatus(bool $status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * Get created time.
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * Set created time.
   */
  public function setCreatedTime(int $timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Get owner.
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * Get owner ID.
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * Set owner ID.
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * Set owner.
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * Get paragraphs.
   */
  public function getParagraphs() {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $paragraphs_field */
    $paragraphs_field = $this->get('paragraphs');
    return $paragraphs_field->referencedEntities();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the lecture entity.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('A boolean indicating whether the lecture is published.'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the lecture author.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the lecture was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the lecture was last edited.'));

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The weight of this term in relation to other terms.'))
      ->setDefaultValue(0);

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
