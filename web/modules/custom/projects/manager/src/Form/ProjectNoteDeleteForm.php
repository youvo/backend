<?php

namespace Drupal\manager\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Asks a manager to confirm the deletion of a note.
 *
 * A plain form that is built and processed inside the logbook context pane
 * (arguments: the project and the entry). The pane reads the outcome from the
 * form state flags 'deleted', 'delete_failed', 'delete_denied' and
 * 'already_submitted' and renders the messages itself.
 */
class ProjectNoteDeleteForm extends FormBase {

  use NoteFormAuthorizationTrait;

  /**
   * Constructs the form.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ManagerMailAccess $access,
    protected FormOnceGuard $once,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('mailer.manager_mail_access'),
      $container->get('manager.form_once'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'manager_project_note_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL, ?CorrespondenceInterface $correspondence = NULL): array {
    $form['#attributes']['class'][] = 'manager-pane-form';
    $form['question'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Do you really want to delete the note "@title"? This cannot be undone.', [
        '@title' => $correspondence?->label() ?? '',
      ]),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
    ];
    $project_id = $project?->id() ?? $correspondence?->get('project')->target_id;
    if ($project_id !== NULL) {
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $project_id], [
          'query' => ['type' => 'logbook', 'view' => 'index'],
        ]),
        '#attributes' => ['data-pane-view' => 'index', 'class' => ['button']],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->authorizedNote($form_state, 'delete') === NULL) {
      $form_state->set('delete_denied', TRUE);
      $form_state->setErrorByName('', $this->t('The note could not be deleted.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Guard 1: only covers a repeated submitForm() call on this one in-memory
    // form state.
    if ($form_state->get('deleted')) {
      return;
    }
    $form_state->set('delete_failed', FALSE);
    // Never trust the restored build info: check access again on the actual
    // project and note, fail closed.
    $authorized = $this->authorizedNote($form_state, 'delete');
    if ($authorized === NULL) {
      $form_state->set('delete_denied', TRUE);
      return;
    }
    $correspondence = $authorized[1];
    // Guard 2: a replayed or id-less POST does not delete again (see
    // FormOnceGuard); the claim is released if the delete fails or throws.
    $deleted = $this->once->runOnce($form_state, function () use ($correspondence): bool {
      try {
        $correspondence->delete();
        return TRUE;
      }
      catch (EntityStorageException $exception) {
        $this->getLogger('manager')->error('A note could not be deleted: @message', ['@message' => $exception->getMessage()]);
        return FALSE;
      }
    });
    if ($deleted === NULL) {
      $form_state->set('already_submitted', TRUE);
      return;
    }
    if (!$deleted) {
      $form_state->set('delete_failed', TRUE);
      $form_state->setRebuild();
      return;
    }
    // The pane shows the confirmation when it sees this flag.
    $form_state->set('deleted', TRUE);
  }

}
