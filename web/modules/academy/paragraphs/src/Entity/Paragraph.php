<?php

namespace Drupal\paragraphs\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the paragraph entity class.
 *
 * @ContentEntityType(
 *   id = "paragraph",
 *   label = @Translation("Paragraph"),
 *   label_collection = @Translation("Paragraphs"),
 *   bundle_label = @Translation("Paragraph type"),
 *   handlers = {
 *     "access" = "Drupal\child_entities\ChildEntityAccessControlHandler",
 *     "list_builder" = "Drupal\paragraphs\ParagraphListBuilder",
 *     "form" = {
 *       "add" = "Drupal\paragraphs\Form\ParagraphForm",
 *       "edit" = "Drupal\paragraphs\Form\ParagraphForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "paragraphs",
 *   data_table = "paragraphs_field_data",
 *   translatable = TRUE,
 *   fieldable = TRUE,
 *   admin_permission = "administer paragraphs",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "bundle" = "bundle",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "parent" = "lecture",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "add-form" = "/academy/co/{course}/le/{lecture}/pa/add/{paragraph_type}",
 *     "add-page" = "/academy/co/{course}/le/{lecture}/pa/add",
 *     "edit-form" = "/academy/co/{course}/le/{lecture}/pa/{paragraph}",
 *     "delete-form" = "/academy/co/{course}/le/{lecture}/pa/{paragraph}/delete",
 *     "collection" = "/academy/co/{course}/le/{lecture}/paragraphs"
 *   },
 *   bundle_entity_type = "paragraph_type",
 *   field_ui_base_route = "entity.paragraph_type.edit_form"
 * )
 */
class Paragraph extends ContentEntityBase implements ChildEntityInterface {

  use EntityChangedTrait;
  use ChildEntityTrait;

  /**
   * {@inheritdoc}
   *
   * When a new paragraph entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   *
   * We set the mandatory lecture value here!
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    if (!isset($values['uid'])) {
      $values['uid'] = \Drupal::currentUser()->id();
    }
    if (!isset($values['lecture']) && $route_match = \Drupal::service('current_route_match')->getParameter('lecture')) {
      $values['lecture'] = $route_match;
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
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Internal Title'))
      ->setDescription(t('The title of the paragraph entity.'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the paragraph author.'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the paragraph was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the paragraph was last edited.'));

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
