<?php

namespace Drupal\projects\Entity;

use Drupal\child_entities\ChildEntityTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\ProjectCommentInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the project comment entity class.
 *
 * @ContentEntityType(
 *   id = "project_comment",
 *   label = @Translation("Project Comment"),
 *   label_collection = @Translation("Project Comments"),
 *   handlers = {
 *     "access" = "Drupal\child_entities\ChildEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "project_comment",
 *   fieldable = TRUE,
 *   admin_permission = "administer project result",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "owner" = "author",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *     "parent" = "project_result",
 *     "weight" = "weight"
 *   }
 * )
 */
class ProjectComment extends ContentEntityBase implements ProjectCommentInterface {

  use ChildEntityTrait;
  use EntityChangedTrait;
  use EntityPublishedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): ProjectCommentInterface {
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

    $fields['author'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The UID of the project comment author.'))
      ->setSetting('target_type', 'user')
      ->setTranslatable(FALSE)
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner');

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Published'))
      ->setDescription(new TranslatableMarkup('A boolean indicating whether the project comment is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Authored on'))
      ->setDescription(new TranslatableMarkup('The time that the project comment was created.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('The time that the project comment was last edited.'));

    $fields['value'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Comment'))
      ->setDescription(new TranslatableMarkup('The comment.'));

    $fields += static::childBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
