<?php

namespace Drupal\paragraphs\Entity;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

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
 *   admin_permission = "administer courses",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "bundle" = "bundle",
 *     "label" = "title",
 *     "owner" = "uid",
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
  use EntityOwnerTrait;
  use ChildEntityTrait;

  /**
   * {@inheritdoc}
   *
   * When a new paragraph entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   *
   * We set the mandatory lecture value here!
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values): void {
    parent::preCreate($storage, $values);
    if (!isset($values['uid'])) {
      $values['uid'] = \Drupal::currentUser()->id();
    }
    if (!isset($values['lecture']) && $route_match = \Drupal::service('current_route_match')->getParameter('lecture')) {
      $values['lecture'] = $route_match;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {

    // Adjust weight depending on existing children.
    if ($this->isNew() && $this->getEntityType()->hasKey('weight')) {
      /** @var \Drupal\lectures\Entity\Lecture $parent */
      $parent = $this->getParentEntity();
      $children = $parent->getParagraphs();
      if (!empty($children)) {
        $max_weight = max(array_map(fn($c) => $c->get('weight')->value, $children));
        $this->set('weight', intval($max_weight) + 1);
      }
    }

    // Add a cache tag for evaluation paragraphs in order to easily identify
    // and invalidate all cached evaluations in a course.
    if ($this->isNew() && $this->bundle() === 'evaluation') {
      $course = $this->getOriginEntity();
      $cache_tags[] = $course->getEntityTypeId() . ':' . $course->id() . ':' . $this->bundle();
      $this->addCacheTags($cache_tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    if (!$this->isNew()) {
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
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setTranslatable(FALSE)
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
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the paragraph author.'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the paragraph was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the paragraph was last edited.'));

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
