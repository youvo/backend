<?php

namespace Drupal\feedback\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feedback\Event\FeedbackCompleteEvent;
use Drupal\feedback\FeedbackInterface;
use Drupal\projects\ProjectInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the feedback entity class.
 *
 * @ContentEntityType(
 *   id = "feedback",
 *   label = @Translation("Feedback"),
 *   label_collection = @Translation("Feedbacks"),
 *   bundle_label = @Translation("Feedback type"),
 *   handlers = {
 *     "view_builder" = "Drupal\feedback\FeedbackViewBuilder",
 *     "access" = "Drupal\feedback\Access\FeedbackEntityAccess",
 *     "list_builder" = "Drupal\feedback\FeedbackListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "feedback",
 *   admin_permission = "administer feedback types",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "bundle",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "author"
 *   },
 *   links = {
 *     "canonical" = "/feedback/{feedback}",
 *     "collection" = "/admin/content/feedback"
 *   },
 *   bundle_entity_type = "feedback_type",
 *   field_ui_base_route = "entity.feedback_type.edit_form"
 * )
 */
class Feedback extends ContentEntityBase implements FeedbackInterface {

  use EntityChangedTrait;
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
  public function setCreatedTime(int $timestamp): FeedbackInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function lock(): FeedbackInterface {
    $this->set('locked', TRUE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked(): bool {
    return !empty($this->get('locked')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function getProject(): ProjectInterface {
    /** @var \Drupal\projects\ProjectInterface $project */
    $project = $this->get('project')->entity;
    return $project;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // This is the first time the feedback is completed.
    if ($this->get('completed')->value > 0 && !$this->isLocked()) {
      \Drupal::service('event_dispatcher')
        ->dispatch(new FeedbackCompleteEvent($this));
      $this->lock();
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Authored on'))
      ->setDescription(new TranslatableMarkup('The time that the feedback was created.'))
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
      ->setDescription(new TranslatableMarkup('The time that the feedback was last edited.'));

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Completed'))
      ->setDefaultValue(0)
      ->setDescription(new TranslatableMarkup('The time that the feedback was completed.'));

    $fields['locked'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Locked'))
      ->setDefaultValue(FALSE)
      ->setDescription(new TranslatableMarkup('The feedback is locked after completion.'));

    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Project'))
      ->setSetting('target_type', 'project')
      ->setDescription(new TranslatableMarkup('The project referenced by this feedback.'))
      ->setTranslatable(FALSE);

    return $fields;
  }

}