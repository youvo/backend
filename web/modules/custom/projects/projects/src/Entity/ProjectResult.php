<?php

namespace Drupal\projects\Entity;

use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectCommentInterface;
use Drupal\projects\ProjectResultInterface;

/**
 * Defines the project result entity class.
 *
 * @ContentEntityType(
 *   id = "project_result",
 *   label = @Translation("Project Result"),
 *   label_collection = @Translation("Project Results"),
 *   handlers = {
 *     "access" = "Drupal\projects\Access\ProjectResultUploadAccess",
 *     "form" = {
 *       "edit" = "Drupal\projects\Form\ProjectResultForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "project_result",
 *   fieldable = TRUE,
 *   admin_permission = "administer projects",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
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

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): static {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFiles(array $file_targets): static {
    $this->set('field_files', NULL);
    foreach ($file_targets as $file_target) {
      $this->get('field_files')->appendItem($file_target);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLinks(array $links): static {
    $this->set('field_hyperlinks', NULL);
    foreach ($links as $link) {
      $this->get('field_hyperlinks')->appendItem($link);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function appendComment(ProjectCommentInterface $comment): static {
    $this->get('project_comments')->appendItem(['target_id' => $comment->id()]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getComments(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $comments_field */
    $comments_field = $this->get('project_comments');
    /** @var \Drupal\projects\ProjectCommentInterface[] $comments */
    $comments = $comments_field->referencedEntities();
    return $comments;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommentByUser(AccountInterface $account): ?string {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $comments_field */
    $comments_field = $this->get('project_comments');
    /** @var \Drupal\projects\ProjectCommentInterface[] $comments */
    $comments = $comments_field->referencedEntities();
    $comments_filtered = array_filter($comments, static fn($c) => $c->getOwnerId() == $account->id());
    $comment = reset($comments_filtered);
    return $comment !== FALSE ? $comment->getComment() : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the project result was created.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the project result was last edited.'));

    $fields['project_comments'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project Comments'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'project_comment')
      ->setTranslatable(FALSE);

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
