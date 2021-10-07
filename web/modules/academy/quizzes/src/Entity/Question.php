<?php

namespace Drupal\quizzes\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\quizzes\QuestionInterface;
use Drupal\user\UserInterface;

/**
 * Defines the question entity class.
 *
 * @ContentEntityType(
 *   id = "question",
 *   label = @Translation("Question"),
 *   label_collection = @Translation("Questions"),
 *   bundle_label = @Translation("Question type"),
 *   handlers = {
 *     "access" = "Drupal\child_entities\ChildEntityAccessControlHandler",
 *     "form" = {
 *       "edit" = "Drupal\quizzes\Form\QuestionForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "questions",
 *   data_table = "questions_field_data",
 *   fieldable = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer questions",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "bundle" = "bundle",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "parent" = "paragraph",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "edit-form" = "/admin/content/lectures/{lecture}/paragraphs/{paragraph}/question/{question}/edit"
 *   },
 *   bundle_entity_type = "question_type",
 *   field_ui_base_route = "entity.question_type.edit_form"
 * )
 */
class Question extends ContentEntityBase implements ChildEntityInterface, QuestionInterface {

  use ChildEntityTrait;
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new question entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    if (!isset($values['uid'])) {
      $values['uid'] = \Drupal::currentUser()->id();
    }
    if (!isset($values['paragraph']) && $route_match = \Drupal::service('current_route_match')->getParameter('paragraph')) {
      $values['paragraph'] = $route_match;
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
  public function setCreatedTime($timestamp) {
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

    $fields['body'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(t('Question'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -5,
        'rows' => 2,
      ]);

    $fields['help'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(t('Help Text'))
      ->setDescription(t('Further explanation to the question.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -4,
      ]);

    $fields['explanation'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(t('Explanation'))
      ->setDescription(t('Explaining the reasoning behind the correct answers.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -1,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the question author.'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the question was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the question was last edited.'));

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
