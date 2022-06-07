<?php

namespace Drupal\logbook\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\logbook\LogEventInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the log event entity class.
 *
 * @ContentEntityType(
 *   id = "log_event",
 *   label = @Translation("Log Event"),
 *   label_collection = @Translation("Log Events"),
 *   bundle_label = @Translation("Log Pattern"),
 *   handlers = {
 *     "view_builder" = "Drupal\logbook\LogEventViewBuilder",
 *     "list_builder" = "Drupal\logbook\LogEventListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\logbook\Form\LogEventForm",
 *       "edit" = "Drupal\logbook\Form\LogEventForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "log_event",
 *   data_table = "log_event_field_data",
 *   translatable = FALSE,
 *   revisionable = FALSE,
 *   admin_permission = "administer log pattern",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/log-event/add/{log_event_type}",
 *     "add-page" = "/admin/content/log-event/add",
 *     "canonical" = "/log_event/{log_event}",
 *     "edit-form" = "/admin/content/log-event/{log_event}/edit",
 *     "delete-form" = "/admin/content/log-event/{log_event}/delete",
 *     "collection" = "/admin/content/log-event"
 *   },
 *   bundle_entity_type = "log_pattern"
 * )
 */
class LogEvent extends ContentEntityBase implements LogEventInterface {

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
  public function setCreatedTime(int $timestamp): LogEventInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject(): ?ContentEntityInterface {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $subject_field */
    $subject_field = $this->get('subject');
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $subject_references */
    $subject_references = $subject_field->referencedEntities();
    return !empty($subject_references) ? reset($subject_references) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject(ContentEntityInterface $subject): LogEventInterface {
    $this->set('subject', $subject->id());
    return $this;
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
      ->setLabel(new TranslatableMarkup('Triggered on'))
      ->setDescription(new TranslatableMarkup('The time that the log event was created.'))
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

    $fields['creatives'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Creatives'))
      ->setDescription(new TranslatableMarkup('The UIDs of referenced creatives.'))
      ->setSetting('target_type', 'user')
      ->setSetting('selection_settings', [
        'include_anonymous' => FALSE,
        'target_bundles' => ['user'],
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE);

    $fields['organizations'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Organizations'))
      ->setDescription(new TranslatableMarkup('The UIDs of referenced organizations.'))
      ->setSetting('target_type', 'user')
      ->setSetting('selection_settings', [
        'include_anonymous' => FALSE,
        'target_bundles' => ['organization'],
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE);

    $fields['subject'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Subject'))
      ->setDescription(new TranslatableMarkup('The subject referenced by this event.'))
      ->setTranslatable(FALSE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(FALSE)
      ->setLabel(new TranslatableMarkup('Message'))
      ->setDescription(new TranslatableMarkup('The message.'));

    return $fields;
  }

}
