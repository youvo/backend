<?php

namespace Drupal\lectures\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\child_entities\Plugin\Field\ComputedChildEntityReferenceFieldItemList;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\lectures\LectureInterface;

/**
 * Defines the lecture entity class.
 *
 * @ContentEntityType(
 *   id = "lecture",
 *   label = @Translation("Lecture"),
 *   label_collection = @Translation("Academy"),
 *   handlers = {
 *     "access" = "Drupal\lectures\LectureAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\lectures\LectureListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
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
 *   admin_permission = "administer lectures",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "parent" = "course",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/courses/{course}/lectures/add",
 *     "edit-form" = "/admin/content/courses/{course}/lectures/{lecture}/edit",
 *     "delete-form" = "/admin/content/lectures/{lecture}/delete",
 *     "collection" = "/admin/content/academy"
 *   }
 * )
 */
class Lecture extends ContentEntityBase implements ChildEntityInterface, LectureInterface {

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
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(bool $status) {
    $this->set('status', $status);
    return $this;
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
  public function setCreatedTime(int $timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
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

    $fields['paragraphs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Computed Children.'))
      ->setSetting('target_type', 'paragraph')
      ->setDescription(t('Computes the paragraphs referencing this lecture.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setComputed(TRUE)
      ->setClass(ComputedChildEntityReferenceFieldItemList::class);

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

  /**
   * Overwrite call toUrl for non-present canonical route.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel == 'canonical') {
      return Url::fromUri('route:<nolink>')->setOptions($options);
    }
    else {
      return parent::toUrl($rel, $options);
    }
  }

}
