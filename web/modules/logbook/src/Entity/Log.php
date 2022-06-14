<?php

namespace Drupal\logbook\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\logbook\LogInterface;
use Drupal\logbook\LogPatternInterface;
use Drupal\logbook\Plugin\Field\ComputedTextMarkupFieldItemList;
use Drupal\logbook\Plugin\Field\ComputedTextProcessedFieldItemList;
use Drupal\projects\ProjectInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user_types\Utility\Profile;

/**
 * Defines the log entity class.
 *
 * @ContentEntityType(
 *   id = "log",
 *   label = @Translation("Log"),
 *   label_collection = @Translation("Logs"),
 *   bundle_label = @Translation("Log Pattern"),
 *   handlers = {
 *     "access" = "Drupal\logbook\LogAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\logbook\LogListBuilder",
 *     "form" = {
 *       "add" = "Drupal\logbook\Form\LogForm",
 *       "edit" = "Drupal\logbook\Form\LogForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "log",
 *   data_table = "log_field_data",
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
 *     "add-form" = "/admin/content/log/add/{log_pattern}",
 *     "add-page" = "/admin/content/log/add",
 *     "canonical" = "/log/{log}",
 *     "edit-form" = "/admin/content/logt/{log}/edit",
 *     "delete-form" = "/admin/content/log/{log}/delete",
 *     "collection" = "/logbook"
 *   },
 *   bundle_entity_type = "log_pattern"
 * )
 */
class Log extends ContentEntityBase implements LogInterface {

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
  public function setCreatedTime(int $timestamp): LogInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasProject(): bool {
    return !$this->get('project')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getProject(): ?ProjectInterface {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $project_field */
    $project_field = $this->get('project');
    /** @var \Drupal\projects\ProjectInterface[] $project_references */
    $project_references = $project_field->referencedEntities();
    return !empty($project_references) ? reset($project_references) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setProject(ProjectInterface $project): LogInterface {
    $this->set('project', $project->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasManager(): bool {
    return !$this->get('manager')->isEmpty();
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
  public function setManager(AccountInterface $manager): LogInterface {
    $this->set('manager', $manager->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCreatives(): bool {
    return !$this->get('creatives')->isEmpty();
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
  public function setCreatives(array $creatives): LogInterface {
    $this->set('creatives', NULL);
    foreach ($creatives as $creative) {
      $this->get('creatives')
        ->appendItem(['target_id' => Profile::id($creative)]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): string {
    return $this->get('message')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $message): LogInterface {
    $this->set('message', $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMisc(): array {
    return Json::decode($this->get('misc')->value ?? '') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setMisc(array $misc): LogInterface {
    if ($encoded = Json::encode($misc)) {
      $this->set('misc', $encoded);
    }
    else {
      \Drupal::logger('logbook')
        ->warning('Unable to encode string for log %type.',
          ['%type' => $this->bundle()]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarkup(): string {
    return $this->get('markup')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPattern(): LogPatternInterface {
    /** @var \Drupal\logbook\LogPatternInterface $log_pattern */
    $log_pattern = $this->get('type')->entity;
    return $log_pattern;
  }

  /**
   * {@inheritdoc}
   *
   * Overwritten method for type hinting.
   */
  public function getOwner() {
    $key = $this->getEntityType()->getKey('owner');
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $this->get($key)->entity;
    return $organization;
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
      ->setDescription(new TranslatableMarkup('The time that the log was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
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

    $fields['manager'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Manager'))
      ->setDescription(new TranslatableMarkup('The UIDs of the referenced manager.'))
      ->setSetting('target_type', 'user')
      ->setSetting('selection_settings', [
        'include_anonymous' => FALSE,
        'target_bundles' => ['user'],
      ])
      ->setTranslatable(FALSE);

    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Project'))
      ->setSetting('target_type', 'project')
      ->setDescription(new TranslatableMarkup('The project referenced by this log.'))
      ->setTranslatable(FALSE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(FALSE)
      ->setLabel(new TranslatableMarkup('Message'))
      ->setDescription(new TranslatableMarkup('The message.'));

    $fields['misc'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Miscellaneous'))
      ->setDescription(new TranslatableMarkup('Stores extra information about this log.'))
      ->setTranslatable(FALSE);

    $fields['processed'] = BaseFieldDefinition::create('cacheable_string')
      ->setLabel(new TranslatableMarkup('Processed Text'))
      ->setDescription(new TranslatableMarkup('Computes the processed text.'))
      ->setComputed(TRUE)
      // @todo Change if not using manual translation anymore.
      ->setTranslatable(FALSE)
      ->setClass(ComputedTextProcessedFieldItemList::class);

    // @todo Move to theming.
    $fields['markup'] = BaseFieldDefinition::create('cacheable_string')
      ->setLabel(new TranslatableMarkup('Markup Text'))
      ->setDescription(new TranslatableMarkup('Computes the markup text.'))
      ->setComputed(TRUE)
      ->setTranslatable(FALSE)
      ->setClass(ComputedTextMarkupFieldItemList::class);

    return $fields;
  }

}
