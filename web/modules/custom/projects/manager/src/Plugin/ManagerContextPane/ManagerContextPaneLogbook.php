<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
 * Views: 'index' (the merged timeline), 'note_edit' and 'note_delete' (the
 * note forms; the 'id' query parameter is the id of the correspondence note).
 */
#[ManagerContextPane(id: "logbook")]
class ManagerContextPaneLogbook extends ManagerContextPaneBase implements ManagerContextPaneViewInterface {

  use NotePaneFormTrait;
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
   */
  protected function buildIndex(Project $project, bool $messages = FALSE): array {
    $build = $this->emptyPane($project);
    if ($messages) {
      $build['content']['messages'] = ['#type' => 'status_messages'];
    }
    $build['content']['rows'] = $this->timelineBuilder->build($project, $this->currentUser);
    return $build;
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
      return $this->buildIndex($project, TRUE);
    }
    if ($outcome === 'failed') {
      $this->messenger->addError($delete ? $this->t('The note could not be deleted.') : $this->t('The note could not be saved.'));
    }
    $build = $this->emptyPane($project);
    $build['content']['messages'] = ['#type' => 'status_messages'];
    $build['content']['form'] = $form;
    return $build;
  }

}
