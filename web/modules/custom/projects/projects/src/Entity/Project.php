<?php

namespace Drupal\projects\Entity;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Event\ProjectCreateEvent;
use Drupal\projects\Plugin\Field\UserIsApplicantFieldItemList;
use Drupal\projects\Plugin\Field\UserIsManagerFieldItemList;
use Drupal\projects\Plugin\Field\UserIsParticipantFieldItemList;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectResultInterface;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user_types\Utility\Profile;

/**
 * Defines the project entity class.
 *
 * @ContentEntityType(
 *   id = "project",
 *   label = @Translation("Project"),
 *   label_collection = @Translation("Projects"),
 *   label_singular = @Translation("project"),
 *   label_plural = @Translation("projects"),
 *   label_count = @PluralTranslation(
 *     singular = "@count project",
 *     plural = "@count projects"
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\projects\Access\ProjectEntityAccess",
 *     "query_access" = "\Drupal\entity\QueryAccess\EventOnlyQueryAccessHandler",
 *     "permission_provider" = "\Drupal\entity\EntityPermissionProvider",
 *     "list_builder" = "Drupal\projects\ProjectListBuilder",
 *     "form" = {
 *       "add" = "Drupal\projects\Form\ProjectAddForm",
 *       "edit" = "Drupal\projects\Form\ProjectForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "project",
 *   data_table = "project_field_data",
 *   translatable = TRUE,
 *   fieldable = TRUE,
 *   admin_permission = "administer projects",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "published" = "status",
 *     "uid" = "uid",
 *     "owner" = "uid"
 *   },
 *   links = {
 *     "canonical" = "/projects/{project}",
 *     "add-form" = "/projects/add",
 *     "edit-form" = "/projects/{project}/edit",
 *     "delete-form" = "/projects/{project}/delete",
 *     "collection" = "/projects"
 *   },
 *   field_ui_base_route = "entity.project.settings"
 * )
 */
class Project extends ContentEntityBase implements ProjectInterface {

  use EntityOwnerTrait;
  use EntityChangedTrait;
  // @todo This is confusing when working with the lifecycle. Maybe replace
  //   with "archived" as entity key.
  use EntityPublishedTrait;

  /**
   * The project lifecycle.
   *
   * @var \Drupal\projects\Service\ProjectLifecycleInterface
   */
  protected ProjectLifecycleInterface $lifecycle;

  /**
   * Calls project lifecycle service which holds/manipulates the state.
   */
  public function lifecycle(): ProjectLifecycleInterface {
    if (!isset($this->lifecycle)) {
      $this->lifecycle = \Drupal::service('project.lifecycle');
      $this->lifecycle->setProject($this);
    }
    return $this->lifecycle;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    if (!$this->isNew()) {
      // Invalidate cache to recalculate the field projects of the organization.
      Cache::invalidateTags($this->getOwner()->getCacheTagsToInvalidate());
    }
    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if ($this->isNew()) {
      // Store the current organization contact in the project. We do this
      // because the contact of the organization may change in the future, and
      // we would like to know who was responsible for past projects.
      $this->set('field_contact', $this->getOwner()->getContact());
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {

    if (!$update) {
      // Add new project result and reference accordingly.
      // @todo Adjust langcode.
      $project_result = ProjectResult::create([
        'project' => ['target_id' => $this->id()],
        'langcode' => 'de',
      ]);
      $project_result->save();
      $this->set('project_result', ['target_id' => $project_result->id()]);
      // It is not ideal to have a recursive save here, but we trust the update
      // argument. Unfortunately, we somehow need to resolve the IDs, which are
      // not available before the first save, to create the relation.
      $this->save();

      // Dispatch a project create event if this is a proper organization.
      if ($this->getOwner()->hasRoleOrganization()) {
        $event = new ProjectCreateEvent($this);
        \Drupal::service('event_dispatcher')->dispatch($event);
      }

      // Invalidate cache to recalculate the field projects of the organization.
      Cache::invalidateTags($this->getOwner()->getCacheTagsToInvalidate());
    }

    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   *
   * This override exists to set the operation to the default value "view".
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $operation = !empty($operation) ? $operation : 'view';
    return parent::access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   *
   * Overwritten method for type hinting.
   */
  public function getOwner(): Organization {
    $key = $this->getEntityType()->getKey('owner');
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $this->get($key)->entity;
    return $organization;
  }

  /**
   * {@inheritdoc}
   */
  public function getApplicants(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $applicants_field */
    $applicants_field = $this->get('field_applicants');
    /** @var \Drupal\creatives\Entity\Creative $applicant */
    foreach ($applicants_field->referencedEntities() as $applicant) {
      $applicants[(int) $applicant->id()] = $applicant;
    }
    return $applicants ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setApplicants(array $applicants): static {
    $this->set('field_applicants', NULL);
    foreach ($applicants as $applicant) {
      $this->get('field_applicants')->appendItem(['target_id' => Profile::id($applicant)]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function appendApplicant(AccountInterface|int $applicant): static {
    $this->get('field_applicants')->appendItem(['target_id' => Profile::id($applicant)]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicant(AccountInterface|int $applicant): bool {
    return array_key_exists(Profile::id($applicant), $this->getApplicants());
  }

  /**
   * {@inheritdoc}
   */
  public function hasApplicant(): bool {
    return !empty($this->getApplicants());
  }

  /**
   * {@inheritdoc}
   */
  public function getParticipants(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $participants_field */
    $participants_field = $this->get('field_participants');
    $tasks = $this->get('field_participants_tasks')->getValue();
    /** @var \Drupal\user\UserInterface $participant */
    foreach ($participants_field->referencedEntities() as $delta => $participant) {
      // @phpstan-ignore-next-line
      $participant->task = $tasks[$delta]['value'];
      $participants[(int) $participant->id()] = $participant;
    }
    return $participants ?? [];
  }

  /**
   * {@inheritdoc}
   *
   * @todo Introduce field type to associate creative and task better.
   */
  public function setParticipants(array $participants, array $tasks = []): static {
    $this->set('field_participants', NULL);
    $this->set('field_participants_tasks', NULL);
    foreach ($participants as $delta => $participant) {
      $this->get('field_participants')->appendItem(['target_id' => Profile::id($participant)]);
      $task = $tasks[$delta] ?? 'Creative';
      $this->get('field_participants_tasks')->appendItem($task);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function appendParticipant(AccountInterface|int $participant, string $task = 'Creative'): static {
    $this->get('field_participants')->appendItem(['target_id' => Profile::id($participant)]);
    $this->get('field_participants_tasks')->appendItem($task);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isParticipant(AccountInterface|int $participant): bool {
    return array_key_exists(Profile::id($participant), $this->getParticipants());
  }

  /**
   * {@inheritdoc}
   */
  public function hasParticipant(?string $task = NULL): bool {

    if ($task === NULL) {
      return !empty($this->getParticipants());
    }

    $tasks = $this->get('field_participants_tasks')->getValue();
    $someone_has_task = in_array($task, array_column($tasks, 'value'), TRUE);
    return !empty($this->getParticipants()) && $someone_has_task;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthor(AccountInterface|int $account): bool {
    return Profile::id($account) == $this->getOwner()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): static {
    $this->set('title', $title);
    return $this;
  }

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
  public function isPromoted(): bool {
    return (bool) $this->get('promote')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPromoted(bool $promoted): static {
    $this->set('promote', $promoted ? ProjectInterface::PROMOTED : ProjectInterface::NOT_PROMOTED);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResult(): ProjectResultInterface {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $result_field */
    $result_field = $this->get('project_result');
    $result_references = $result_field->referencedEntities();
    if (empty($result_references)) {
      throw new EntityStorageException('Unable to load result of project.');
    }
    /** @var \Drupal\projects\ProjectResultInterface $result */
    $result = reset($result_references);
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Project Title'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The UID of the project author.'))
      ->setSetting('target_type', 'user')
      ->setTranslatable(FALSE)
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setTranslatable(FALSE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 120,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the project was created.'))
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the project was last edited.'))
      ->setTranslatable(FALSE);

    $fields['promote'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Promoted to front page.'))
      ->setTranslatable(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 15,
      ]);

    $fields['project_result'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project Result'))
      ->setSetting('target_type', 'project_result')
      ->setTranslatable(FALSE);

    $fields['user_is_applicant'] = BaseFieldDefinition::create('cacheable_boolean')
      ->setLabel(t('User Status Applicant'))
      ->setDescription(t('Computes the applicant status for user.'))
      ->setComputed(TRUE)
      ->setTranslatable(FALSE)
      ->setClass(UserIsApplicantFieldItemList::class);

    $fields['user_is_participant'] = BaseFieldDefinition::create('cacheable_boolean')
      ->setLabel(t('User Status Participant'))
      ->setDescription(t('Computes the participant status for user.'))
      ->setComputed(TRUE)
      ->setTranslatable(FALSE)
      ->setClass(UserIsParticipantFieldItemList::class);

    $fields['user_is_manager'] = BaseFieldDefinition::create('cacheable_boolean')
      ->setLabel(t('User Status Manager'))
      ->setDescription(t('Computes the manager status for user.'))
      ->setComputed(TRUE)
      ->setTranslatable(FALSE)
      ->setClass(UserIsManagerFieldItemList::class);

    return $fields;
  }

}
