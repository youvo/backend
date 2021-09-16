<?php

namespace Drupal\quizzes\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "questions",
 *   data_table = "questions_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer questions",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "bundle" = "type",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "parent" = "paragraph",
 *     "weight" = "weight"
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
      ->setLabel(new TranslatableMarkup('Question'))
      ->setDescription(new TranslatableMarkup('The question.'))
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'rows' => 2,
        'weight' => 10,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => 10,
      ]);

    $fields['help'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Help Text'))
      ->setDescription(new TranslatableMarkup('Further explanation to the question.'))
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'rows' => 3,
        'weight' => 11,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => 11,
      ]);

    $fields['options'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Answer Options'))
      ->setDescription(new TranslatableMarkup('&-separated options for the answers.'))
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'rows' => 3,
        'placeholder' => new TranslatableMarkup('Option 1 &amp;&#10;Option 2 &amp;&#10;Option 3'),
        'weight' => 12,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => 12,
      ]);

    $fields['answers'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Correct Answer(s)'))
      ->setDescription(new TranslatableMarkup('&-separated numbers of correct answers. Only one for single-choice question.'))
      ->setDisplayOptions('form', [
        'type' => 'textfield',
        'placeholder' => new TranslatableMarkup('1 &amp; 2 &amp; 3'),
        'weight' => 12,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => 12,
      ]);

    $fields['explanation'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Explanation'))
      ->setDescription(new TranslatableMarkup('Explaining the reasoning behind the correct answers.'))
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => 12,
        'rows' => 3,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => 12,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Author'))
      ->setDescription(new TranslatableMarkup('The user ID of the question author.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(new TranslatableMarkup('The time that the question was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(new TranslatableMarkup('The time that the question was last edited.'));

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Weight'))
      ->setDescription(new TranslatableMarkup('The weight of this term in relation to other terms.'))
      ->setDefaultValue(0);

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
