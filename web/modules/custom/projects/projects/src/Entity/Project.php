<?php

namespace Drupal\projects\Entity;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Event\ProjectChangeEvent;
use Drupal\projects\Event\ProjectCreateEvent;
use Drupal\projects\Plugin\Field\UserIsApplicantFieldItemList;
use Drupal\projects\Plugin\Field\UserIsManagerFieldItemList;
use Drupal\projects\Plugin\Field\UserIsParticipantFieldItemList;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectResultInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user_types\Utility\Profile;

/**
 * Defines the project entity class.
 *
 * The published key (status) means active: an unpublished project is
 * archived. Hidden projects are active, but not listed publicly.
 */
#[ContentEntityType(
  id: 'project',
  label: new TranslatableMarkup('Project'),
  label_collection: new TranslatableMarkup('Projects'),
  label_singular: new TranslatableMarkup('project'),
  label_plural: new TranslatableMarkup('projects'),
  label_count: [
    'singular' => '@count project',
    'plural' => '@count projects',
  ],
  handlers: [
    'view_builder' => 'Drupal\Core\Entity\EntityViewBuilder',
    'access' => 'Drupal\projects\Access\ProjectEntityAccess',
    'query_access' => '\Drupal\entity\QueryAccess\EventOnlyQueryAccessHandler',
    'permission_provider' => '\Drupal\entity\EntityPermissionProvider',
    'list_builder' => 'Drupal\projects\ProjectListBuilder',
    'form' => [
      'add' => 'Drupal\projects\Form\ProjectAddForm',
      'edit' => 'Drupal\projects\Form\ProjectForm',
      'delete' => 'Drupal\Core\Entity\EntityDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\projects\Routing\ProjectHtmlRouteProvider',
      'revision' => 'Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider',
    ],
    'views_data' => 'Drupal\views\EntityViewsData',
  ],
  base_table: 'project',
  data_table: 'project_field_data',
  revision_table: 'project_revision',
  revision_data_table: 'project_field_revision',
  show_revision_ui: FALSE,
  translatable: TRUE,
  admin_permission: 'administer projects',
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'label' => 'title',
    'langcode' => 'langcode',
    'uuid' => 'uuid',
    'status' => 'status',
    'published' => 'status',
    'uid' => 'uid',
    'owner' => 'uid',
    'revision_translation_affected' => 'revision_translation_affected',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_user',
    'revision_created' => 'revision_created',
    'revision_log_message' => 'revision_log_message',
    'revision_default' => 'revision_default',
  ],
  links: [
    'canonical' => '/projects/{project}',
    'add-form' => '/projects/add',
    'edit-form' => '/projects/{project}/edit',
    'delete-form' => '/projects/{project}/delete',
    'revision' => '/projects/{project}/revisions/{project_revision}/view',
    'collection' => '/projects',
  ],
  field_ui_base_route: 'entity.project.settings',
  additional: [
    'fieldable' => TRUE,
  ],
)]
class Project extends RevisionableContentEntityBase implements ProjectInterface {

  use EntityOwnerTrait;
  use EntityChangedTrait;
  // @todo This is confusing when working with the lifecycle. Maybe replace
  //   with "archived" as entity key.
  use EntityPublishedTrait;

  /**
   * The project lifecycle, created on the first call of lifecycle().
   */
  protected ?ProjectLifecycleInterface $lifecycle = NULL;

  /**
   * The content fields changed in the last save, in CONTENT_FIELDS order.
   *
   * @var string[]
   */
  protected array $changedContentFields = [];

  /**
   * The loaded revision ID before the last save.
   */
  protected ?int $previousRevisionId = NULL;

  /**
   * Whether the project was past draft before the last save.
   */
  protected bool $wasSubmitted = FALSE;

  /**
   * {@inheritdoc}
   *
   * The lifecycle is a non-shared service, which can't be serialized by
   * reference. It is created again on the next call of lifecycle().
   */
  public function __sleep(): array {
    return array_diff(parent::__sleep(), ['lifecycle']);
  }

  /**
   * {@inheritdoc}
   *
   * A clone gets its own lifecycle; the one of the original points to the
   * original.
   */
  public function __clone() {
    parent::__clone();
    $this->lifecycle = NULL;
  }

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
  public function postCreate(EntityStorageInterface $storage): void {
    // Set first item in lifecycle history.
    if ($this->hasField('field_lifecycle_history')) {
      $this->set('field_lifecycle_history', [
        'transition' => NULL,
        'from' => NULL,
        'to' => ProjectState::Draft->value,
        'uid' => \Drupal::currentUser()->id(),
        'timestamp' => $this->getCreatedTime(),
      ]);
    }
    parent::postCreate($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {

    $this->changedContentFields = [];
    $this->previousRevisionId = NULL;
    $this->wasSubmitted = FALSE;
    if (!$this->isNew() && !$this->isSyncing()) {
      $this->changedContentFields = $this->computeChangedContentFields();
      $this->previousRevisionId = (int) $this->getLoadedRevisionId();
      $this->wasSubmitted = $this->getOriginal() instanceof ProjectInterface && !$this->getOriginal()->lifecycle()->isDraft();
      if ($this->changedContentFields) {
        $this->setNewRevision();
        $this->setRevisionUserId(\Drupal::currentUser()->id());
        $this->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $this->setRevisionLogMessage(implode(', ', $this->changedContentFields));
      }
    }

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
      // not available before the first save, to create the relation. The
      // storage marks the first revision as stored only after postSave(), so
      // the second save would insert it again.
      $this->updateLoadedRevisionId();
      $this->setNewRevision(FALSE);
      $this->save();

      // Dispatch a project create event if this is a proper organization.
      if ($this->getOwner()->hasRoleOrganization()) {
        $event = new ProjectCreateEvent($this, $this->getCreatedTime());
        \Drupal::service('event_dispatcher')->dispatch($event);
      }

      // Invalidate cache to recalculate the field projects of the organization.
      Cache::invalidateTags($this->getOwner()->getCacheTagsToInvalidate());
    }

    // Content changes after draft are worth a note for the managers, if this
    // is a proper organization like for the create event.
    if (
      $update &&
      $this->changedContentFields &&
      $this->wasSubmitted &&
      $this->getOwner() instanceof Organization &&
      $this->getOwner()->hasRoleOrganization()
    ) {
      $event = new ProjectChangeEvent($this, $this->changedContentFields, (int) $this->previousRevisionId, (int) $this->getRevisionId());
      \Drupal::service('event_dispatcher')->dispatch($event);
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
  public function getOwner(): Organization|Creative {
    $key = $this->getEntityType()->getKey('owner');
    /** @var \Drupal\organizations\Entity\Organization|\Drupal\creatives\Entity\Creative $owner */
    $owner = $this->get($key)->entity;
    if (!$owner instanceof Organization && !$owner->hasPermission('administer site')) {
      throw new \LogicException('The owner of a project should be an organization');
    }
    return $owner;
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
  public function getParticipants(?string $task = NULL): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $participants_field */
    $participants_field = $this->get('field_participants');
    $tasks = $this->get('field_participants_tasks')->getValue();
    /** @var \Drupal\user\UserInterface $participant */
    foreach ($participants_field->referencedEntities() as $delta => $participant) {
      // @phpstan-ignore-next-line
      $participant->task = $tasks[$delta]['value'];
      $participants[(int) $participant->id()] = $participant;
    }
    if ($task !== NULL && !empty($participants)) {
      // @phpstan-ignore-next-line
      $participants = array_filter($participants, static fn ($p) => $p->task === $task);
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
  public function isActive(): bool {
    return $this->isPublished();
  }

  /**
   * {@inheritdoc}
   */
  public function isArchived(): bool {
    return !$this->isActive();
  }

  /**
   * {@inheritdoc}
   */
  public function setArchived(bool $archived = TRUE): static {
    return $archived ? $this->setUnpublished() : $this->setPublished();
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden(): bool {
    return (bool) $this->get('hidden')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHidden(bool $hidden = TRUE): static {
    $this->set('hidden', $hidden);
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
      ->setRevisionable(TRUE)
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
      ->setDisplayConfigurable('view', TRUE)
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
      ->setLabel(t('Archive status'))
      ->setDescription(t("Archived projects are offline: only supervisors and administrators can see them, and they don't transition."))
      ->setSetting('on_label', t('Active'))
      ->setSetting('off_label', t('Archived'))
      ->setTranslatable(FALSE)
      ->setDefaultValue(TRUE)
      ->setDisplayConfigurable('view', TRUE)
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
      ->setDisplayConfigurable('view', TRUE)
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
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['promote'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Promoted to front page.'))
      ->setTranslatable(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 15,
      ]);

    $fields['hidden'] = static::hiddenFieldDefinition();

    $fields['project_result'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project Result'))
      ->setSetting('target_type', 'project_result')
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE);

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

  /**
   * {@inheritdoc}
   */
  public function getChangedContentFields(): array {
    return $this->changedContentFields;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousRevisionId(): ?int {
    return $this->previousRevisionId;
  }

  /**
   * {@inheritdoc}
   *
   * Every translation object reports the change set of the last save.
   */
  protected function initializeTranslation($langcode) {
    /** @var static $translation */
    $translation = parent::initializeTranslation($langcode);
    $translation->changedContentFields = &$this->changedContentFields;
    $translation->previousRevisionId = &$this->previousRevisionId;
    $translation->wasSubmitted = &$this->wasSubmitted;
    return $translation;
  }

  /**
   * Computes the content fields that differ from the original project.
   *
   * Every translation counts. A translation the original lacks changes its
   * non-empty content fields.
   *
   * @return string[]
   *   The changed field names, in CONTENT_FIELDS order.
   */
  protected function computeChangedContentFields(): array {

    $original = $this->getOriginal();
    if (!$original instanceof ProjectInterface) {
      return [];
    }

    $changed = [];
    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);
      $original_translation = $original->hasTranslation($langcode) ? $original->getTranslation($langcode) : NULL;
      foreach (static::CONTENT_FIELDS as $field_name) {
        // Sites without a configurable field, such as kernel tests, skip it.
        if (!$translation->hasField($field_name)) {
          continue;
        }
        $items = $translation->get($field_name);
        $original_items = $original_translation?->get($field_name);
        $changed_items = match (TRUE) {
          $original_items === NULL => !$items->isEmpty(),
          // Only another file changes the image. The image fills in its
          // dimensions on save, after this comparison.
          $field_name === 'field_image' => $items->hasAffectingChanges($original_items, $langcode) &&
            array_column($items->getValue(), 'target_id') != array_column($original_items->getValue(), 'target_id'),
          default => $items->hasAffectingChanges($original_items, $langcode),
        };
        if ($changed_items) {
          $changed[$field_name] = $field_name;
        }
      }
    }

    return array_values(array_intersect(static::CONTENT_FIELDS, $changed));
  }

  /**
   * Gets the definition of the hidden field, shared with the update hook.
   */
  public static function hiddenFieldDefinition(): BaseFieldDefinition {
    return BaseFieldDefinition::create('boolean')
      ->setLabel(t('Hidden'))
      ->setDescription(t('Hidden projects are not listed publicly and take no applications. The organization still sees them.'))
      ->setTranslatable(FALSE)
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('view', TRUE);
  }

}
