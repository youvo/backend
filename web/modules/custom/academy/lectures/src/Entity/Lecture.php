<?php

namespace Drupal\lectures\Entity;

use Drupal\academy\AcademicFormatInterface;
use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

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
 *     "owner" = "uid",
 *     "published" = "status",
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
  use EntityPublishedTrait;
  use EntityOwnerTrait;
  use ChildEntityTrait;

  /**
   * {@inheritdoc}
   *
   * When a new lecture entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   *
   * We set the mandatory course value here!
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values): void {
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
  public function preSave(EntityStorageInterface $storage): void {
    // Adjust weight depending on existing children.
    if ($this->isNew() && $this->getEntityType()->hasKey('weight')) {
      /** @var \Drupal\courses\Entity\Course $parent */
      $parent = $this->getParentEntity();
      $children = $parent->getLectures();
      if (!empty($children)) {
        $max_weight = max(array_map(static fn($c) => $c->get('weight')->value, $children));
        $this->set('weight', (int) $max_weight + 1);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    if (!$this->isNew()) {
      // Delete all referenced paragraphs.
      $paragraphs = $this->getParagraphs();
      foreach ($paragraphs as $paragraph) {
        $paragraph->delete();
      }
      // Invalidate parent cache to update the computed children field.
      $this->invalidateParentCache();
    }
    parent::delete();
  }

  /**
   * Gets the title.
   */
  public function getTitle(): string {
    return $this->get('title')->value;
  }

  /**
   * Sets the title.
   */
  public function setTitle(string $title): static {
    $this->set('title', $title);
    return $this;
  }

  /**
   * Gets the created time.
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * Sets the created time.
   */
  public function setCreatedTime(int $timestamp): static {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Gets the referenced paragraphs.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph[]
   *   The referenced paragraphs.
   */
  public function getParagraphs(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $paragraphs_field */
    $paragraphs_field = $this->get('paragraphs');
    /** @var \Drupal\paragraphs\Entity\Paragraph[] $paragraphs */
    $paragraphs = $paragraphs_field->referencedEntities();
    return $paragraphs;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

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
      ->setDescription(t('The time that the lecture was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the lecture was last edited.'));

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
