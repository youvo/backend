<?php

namespace Drupal\logbook\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\logbook\LogEventInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\user\EntityOwnerTrait;
use Drupal\user_types\Utility\Profile;

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
 *     "add-form" = "/admin/content/log-event/add/{log_pattern}",
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
   */
  public function getObject(): ?ContentEntityInterface {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $object_field */
    $object_field = $this->get('object');
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $object_references */
    $object_references = $object_field->referencedEntities();
    return !empty($object_references) ? reset($object_references) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setObject(ContentEntityInterface $object): LogEventInterface {
    $this->set('object', $object->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getManager(): ?Creative {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $manager_field */
    $manager_field = $this->get('manager');
    /** @var \Drupal\creatives\Entity\Creative[] $manager_references */
    $manager_references = $manager_field->referencedEntities();
    return !empty($manager_references) ? reset($manager_references) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setManager(AccountInterface $manager): LogEventInterface {
    $this->set('manager', $manager->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): ?Organization {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $organization_field */
    $organization_field = $this->get('manager');
    /** @var \Drupal\organizations\Entity\Organization[] $organization_references */
    $organization_references = $organization_field->referencedEntities();
    return !empty($organization_references) ? reset($organization_references) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrganization(Organization $organization): LogEventInterface {
    $this->set('organization', $organization->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatives(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $creatives_field */
    $creatives_field = $this->get('creatives');
    /** @var \Drupal\creatives\Entity\Creative $creative */
    foreach ($creatives_field->referencedEntities() as $creative) {
      $creatives[intval($creative->id())] = $creative;
    }
    return $creatives ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatives(array $creatives): LogEventInterface {
    $this->set('creatives', NULL);
    foreach ($creatives as $creative) {
      $this->get('creatives')
        ->appendItem(['target_id' => Profile::id($creative)]);
    }
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
      ->setDescription(new TranslatableMarkup('The UIDs of the referenced creatives.'))
      ->setSetting('target_type', 'user')
      ->setSetting('selection_settings', [
        'include_anonymous' => FALSE,
        'target_bundles' => ['user'],
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE);

    $fields['organization'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Organization'))
      ->setDescription(new TranslatableMarkup('The UIDs of the referenced organization.'))
      ->setSetting('target_type', 'user')
      ->setSetting('selection_settings', [
        'include_anonymous' => FALSE,
        'target_bundles' => ['organization'],
      ])
      ->setTranslatable(FALSE);

    $fields['manager'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Manager'))
      ->setDescription(new TranslatableMarkup('The UIDs of the referenced manager.'))
      ->setSetting('target_type', 'user')
      ->setSetting('selection_settings', [
        'include_anonymous' => FALSE,
        'target_bundles' => ['user'],
      ])
      ->setTranslatable(FALSE);

    $fields['subject'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Subject'))
      ->setDescription(new TranslatableMarkup('The subject referenced by this event.'))
      ->setTranslatable(FALSE);

    $fields['object'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Object'))
      ->setDescription(new TranslatableMarkup('The object referenced by this event.'))
      ->setTranslatable(FALSE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(FALSE)
      ->setLabel(new TranslatableMarkup('Message'))
      ->setDescription(new TranslatableMarkup('The message.'));

    return $fields;
  }

}
