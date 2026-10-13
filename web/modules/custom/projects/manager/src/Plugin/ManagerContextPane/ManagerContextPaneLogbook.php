<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\manager\Form\ProjectNoteDeleteForm;
use Drupal\manager\Form\ProjectNoteForm;
use Drupal\manager\Timeline\TimelineBuilder;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a project logbook manager context pane.
 *
 * Views: 'index' (the merged timeline with the add row), 'note_add' (the
 * timeline with the add form in place of the add row), 'note_edit' and
 * 'note_delete' (the timeline with the edit form, or the delete confirmation,
 * in the row of the note; the 'id' query parameter is the id of the
 * correspondence note). Every view is the whole timeline: the script takes the
 * row it needs from it, so several forms can be open at once.
 */
#[ManagerContextPane(id: "logbook")]
class ManagerContextPaneLogbook extends ManagerContextPaneBase implements ManagerContextPaneViewInterface {

  use NotePaneFormTrait;
  use PaneMessagesTrait;
  use StringTranslationTrait;

  /**
   * The timeline builder.
   */
  protected TimelineBuilder $timelineBuilder;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The access service.
   */
  protected ManagerMailAccess $access;

  /**
   * The form builder.
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The messenger.
   */
  protected MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->timelineBuilder = $container->get('manager.timeline_builder');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->access = $container->get('mailer.manager_mail_access');
    $instance->formBuilder = $container->get('form_builder');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {
    return $this->buildIndex($project);
  }

  /**
   * {@inheritdoc}
   */
  public function buildView(Project $project, Request $request): array {
    $view = $request->query->get('view');
    if ($view === 'note_add') {
      return $this->buildAddView($project, $request);
    }
    if ($view === 'note_edit' || $view === 'note_delete') {
      return $this->buildNoteView($project, $request, $view);
    }
    return $this->buildIndex($project);
  }

  /**
   * Builds the empty pane.
   */
  protected function emptyPane(Project $project): array {
    return ['#theme' => 'context_pane', '#type' => 'logbook', '#project' => $project, 'content' => []];
  }

  /**
   * Builds the timeline view.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project.
   * @param bool $messages
   *   TRUE to render the status messages above the timeline.
   * @param array|null $add_form
   *   The add note form to show in place of the add row, if any.
   * @param int|null $highlight
   *   The id of the note to highlight, the one that was just saved.
   * @param array<int, array{0: string, 1: array}> $note_forms
   *   The form to show in the row of a note, keyed by the note id: 'edit' or
   *   'delete' and the form.
   */
  protected function buildIndex(Project $project, bool $messages = FALSE, ?array $add_form = NULL, ?int $highlight = NULL, array $note_forms = []): array {
    $build = $this->emptyPane($project);
    if ($messages) {
      $build['content']['messages'] = $this->buildPaneMessages($this->messenger);
    }
    $may_add = $this->mayAdd($project);
    if ($may_add && $add_form === NULL) {
      $build['content']['add'] = $this->buildAddRow($project);
    }
    $metadata = new CacheableMetadata();
    $rows = $this->timelineBuilder->build($project, $this->currentUser, $metadata);
    foreach ($rows as &$row) {
      $row['key'] = $row['type'] . '-' . $row['id'];
      $row['highlight'] = $highlight !== NULL && $row['type'] === 'note' && $row['id'] === $highlight;
      if ($row['type'] === 'note' && isset($note_forms[$row['id']])) {
        [$kind, $form] = $note_forms[$row['id']];
        $row[$kind === 'edit' ? 'form' : 'delete_form'] = $form;
      }
    }
    unset($row);
    $timeline = [
      '#theme' => 'manager_timeline',
      '#rows' => $rows,
      '#add_form' => $may_add ? $add_form : NULL,
    ];
    // Rendering the rows is the expensive part (a long timeline has some
    // hundred entries), so the plain timeline is render cached. The access
    // and the links depend on the user. A timeline with a form, or a row that
    // is highlighted, is rendered once after a submit and not cached.
    if (!($may_add && $add_form !== NULL) && $highlight === NULL && $note_forms === []) {
      $metadata->addCacheContexts(['user'])
        ->addCacheTags([TimelineBuilder::cacheTag($project->id())])
        ->addCacheableDependency($project)
        ->applyTo($timeline);
      $timeline['#cache']['keys'] = ['manager', 'timeline', $project->id()];
    }
    $build['content']['timeline'] = $timeline;
    return $build;
  }

  /**
   * Gets the id of the note the form saved during this request.
   */
  protected function savedNote(Request $request): ?int {
    $id = $request->attributes->get(ProjectNoteForm::SAVED_ATTRIBUTE);
    return is_int($id) ? $id : NULL;
  }

  /**
   * Checks if the current user may add a note to the project.
   */
  protected function mayAdd(Project $project): bool {
    return $this->entityTypeManager->getAccessControlHandler('correspondence')->createAccess('note', $this->currentUser)
      && $this->access->noteAccess($this->currentUser, $project)->isAllowed();
  }

  /**
   * Builds the ghost row that opens the add note form in place.
   */
  protected function buildAddRow(Project $project): array {
    $label = $this->t('Add note');
    return [
      '#type' => 'link',
      '#title' => [
        '#markup' => '<span class="logbook-timeline-header-container"><span class="logbook-timeline-label-container"><span class="logbook-timeline-label">+ ' . $this->t('Note') . '</span></span>'
        . '<span class="logbook-timeline-date-container"></span></span>'
        . '<span class="logbook-timeline-bar-container"><span class="logbook-timeline-circle"></span></span>'
        . '<span class="logbook-timeline-content-container logbook-timeline-text">' . $label . '…</span>',
      ],
      '#url' => Url::fromRoute('manager.context_pane', ['project' => $project->id()], [
        'query' => ['type' => 'logbook', 'view' => 'note_add'],
      ]),
      '#attributes' => [
        'class' => ['logbook-timeline-flex-row', 'logbook-timeline-add'],
        'data-row-key' => 'add',
        'data-pane-view' => 'note_add',
        'data-pane-row' => 'add',
        'data-pane-autofocus' => '',
        'title' => $label,
      ],
    ];
  }

  /**
   * Builds the add note view; access is checked on every request.
   *
   * A valid POST saves the note in the form's submit handler and returns the
   * timeline with a confirmation; a failed save returns the timeline with the
   * form again.
   */
  protected function buildAddView(Project $project, Request $request): array {
    if (!$this->mayAdd($project)) {
      return $this->emptyPane($project);
    }
    [$outcome, $form] = $this->processNoteForm($this->formBuilder, ProjectNoteForm::class, $project, NULL, $request, 'saved', 'save_failed', 'save_denied');
    if ($outcome === 'denied') {
      return $this->emptyPane($project);
    }
    if ($outcome === 'already') {
      $this->messenger->addStatus($this->t('This form was already submitted.'));
      return $this->buildIndex($project, TRUE);
    }
    if ($outcome === 'done') {
      $this->messenger->addStatus($this->t('The note was saved.'));
      return $this->buildIndex($project, TRUE, NULL, $this->savedNote($request));
    }
    if ($outcome === 'form') {
      // The form shows its errors at the fields.
      $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
    }
    if ($outcome === 'failed') {
      $this->messenger->addError($this->t('The note could not be saved.'));
    }
    return $this->buildIndex($project, TRUE, $form);
  }

  /**
   * Loads the note of the request if it may be used in this view.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project of the pane URL.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, its 'id' query parameter is the note id.
   * @param string $operation
   *   The entity operation the view needs ('update' or 'delete').
   *
   * @return \Drupal\correspondence\CorrespondenceInterface|null
   *   The note, or NULL if the id is missing, the note does not exist or
   *   belongs to another project, or the account may not change it.
   */
  protected function loadNote(Project $project, Request $request, string $operation): ?CorrespondenceInterface {
    $id = $request->query->get('id');
    if (!is_string($id) || !ctype_digit($id)) {
      return NULL;
    }
    $entry = $this->entityTypeManager->getStorage('correspondence')->load($id);
    if (
      !$entry instanceof CorrespondenceInterface
      || !$entry->isNote()
      || (string) $entry->get('project')->target_id !== (string) $project->id()
      || !$this->access->noteEntryAccess($this->currentUser, $entry)->isAllowed()
      || !$entry->access($operation, $this->currentUser)
    ) {
      return NULL;
    }
    return $entry;
  }

  /**
   * Builds the edit or delete note view; access is checked on every request.
   *
   * A valid POST saves or deletes the note in the form's submit handler and
   * returns the timeline with a confirmation; a failed action returns the form
   * again.
   */
  protected function buildNoteView(Project $project, Request $request, string $view): array {
    $delete = $view === 'note_delete';
    $entry = $this->loadNote($project, $request, $delete ? 'delete' : 'update');
    if ($entry === NULL) {
      return $this->emptyPane($project);
    }
    [$outcome, $form] = $delete
      ? $this->processNoteForm($this->formBuilder, ProjectNoteDeleteForm::class, $project, $entry, $request, 'deleted', 'delete_failed', 'delete_denied')
      : $this->processNoteForm($this->formBuilder, ProjectNoteForm::class, $project, $entry, $request, 'saved', 'save_failed', 'save_denied');
    if ($outcome === 'denied') {
      return $this->emptyPane($project);
    }
    if ($outcome === 'already') {
      $this->messenger->addStatus($this->t('This form was already submitted.'));
      return $this->buildIndex($project, TRUE);
    }
    if ($outcome === 'done') {
      $this->messenger->addStatus($delete ? $this->t('The note was deleted.') : $this->t('The note was saved.'));
      return $this->buildIndex($project, TRUE, NULL, $delete ? NULL : $this->savedNote($request));
    }
    if ($outcome === 'form' && !$delete) {
      // The form shows its errors at the fields.
      $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
    }
    if ($outcome === 'failed') {
      $this->messenger->addError($delete ? $this->t('The note could not be deleted.') : $this->t('The note could not be saved.'));
    }
    // The form in the row of its note, in the whole timeline.
    return $this->buildIndex($project, TRUE, NULL, NULL, [
      (int) $entry->id() => [$delete ? 'delete' : 'edit', $form],
    ]);
  }

}
