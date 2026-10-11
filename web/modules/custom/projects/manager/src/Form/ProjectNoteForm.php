<?php

namespace Drupal\manager\Form;

use Drupal\Core\Datetime\DrupalDateTime;
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
 * Lets a manager add or edit a note on a project.
 *
 * A plain form that is built and processed inside a manager context pane: the
 * mail pane adds a note (arguments: the project), the logbook pane edits one
 * (arguments: the project and the entry). The pane reads the outcome from the
 * form state flags 'saved', 'save_failed', 'save_denied' and
 * 'already_submitted' and renders the messages itself. Everything is plain
 * text.
 */
class ProjectNoteForm extends FormBase {

  use NoteFormAuthorizationTrait;

  /**
   * The earliest accepted time (1970-01-02), so the value is positive.
   */
  protected const MIN_TIMESTAMP = 86400;

  /**
   * The latest accepted time (2037-12-31), within a 32 bit integer column.
   */
  protected const MAX_TIMESTAMP = 2145916799;

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
    return 'manager_project_note_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL, ?CorrespondenceInterface $correspondence = NULL): array {
    $form['#attributes']['class'][] = 'manager-pane-form';

    $timezone = $this->siteTimezone();
    $occurred = $correspondence instanceof CorrespondenceInterface
      ? DrupalDateTime::createFromTimestamp($correspondence->getOccurredTime(), $timezone)
      : new DrupalDateTime('now', $timezone);

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $correspondence?->get('subject')->value ?? '',
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Note'),
      '#default_value' => $correspondence?->get('body')->value ?? '',
      '#rows' => 6,
      '#required' => TRUE,
      '#description' => $this->t('Plain text.'),
    ];
    $form['occurred'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date and time'),
      '#default_value' => $occurred,
      '#date_timezone' => $timezone,
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    $project_id = $project?->id() ?? $correspondence?->get('project')->target_id;
    if ($project_id !== NULL) {
      // The add form lives in the mail pane, the edit form in the logbook.
      $pane = $correspondence instanceof CorrespondenceInterface ? 'logbook' : 'mail';
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $project_id], [
          'query' => ['type' => $pane, 'view' => 'index'],
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
    [, $cached_entry] = $this->buildArguments($form_state);
    if ($this->authorizedNote($form_state, $cached_entry ? 'update' : 'create') === NULL) {
      $form_state->set('save_denied', TRUE);
      $form_state->setErrorByName('', $this->t('The note could not be saved.'));
      return;
    }
    if (trim((string) $form_state->getValue('subject')) === '') {
      $form_state->setErrorByName('subject', $this->t('The title is required.'));
    }
    elseif (mb_strlen((string) $form_state->getValue('subject')) > 255) {
      $form_state->setErrorByName('subject', $this->t('The title is too long.'));
    }
    if (trim((string) $form_state->getValue('body')) === '') {
      $form_state->setErrorByName('body', $this->t('The note is required.'));
    }
    $occurred = $this->occurredTimestamp($form_state);
    if ($occurred === NULL) {
      $form_state->setErrorByName('occurred', $this->t('The date and time are required.'));
    }
    elseif ($occurred < self::MIN_TIMESTAMP || $occurred > self::MAX_TIMESTAMP) {
      $form_state->setErrorByName('occurred', $this->t('The date must be between 1970 and 2037.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Guard 1: only covers a repeated submitForm() call on this one in-memory
    // form state.
    if ($form_state->get('saved')) {
      return;
    }
    $form_state->set('save_failed', FALSE);
    // Never trust the restored build info: check access again on the actual
    // project and note, fail closed.
    [, $cached_entry] = $this->buildArguments($form_state);
    $authorized = $this->authorizedNote($form_state, $cached_entry ? 'update' : 'create');
    if ($authorized === NULL) {
      $form_state->set('save_denied', TRUE);
      return;
    }
    [$project, $correspondence] = $authorized;
    $values = [
      'subject' => trim((string) $form_state->getValue('subject')),
      'body' => ['value' => (string) $form_state->getValue('body'), 'format' => 'plain_text'],
      'occurred' => $this->occurredTimestamp($form_state),
    ];
    // Guard 2: a replayed POST (same form_build_id, still a valid token)
    // rebuilds the form and would save again; a POST without a usable
    // form_build_id is refused as well (fail closed). The build id is claimed
    // before saving and released again if the save fails or throws.
    $saved = $this->once->runOnce($form_state, function () use ($project, $correspondence, $values): bool {
      try {
        if ($correspondence instanceof CorrespondenceInterface) {
          foreach ($values as $name => $value) {
            $correspondence->set($name, $value);
          }
          $correspondence->save();
        }
        else {
          $this->entityTypeManager->getStorage('correspondence')->create($values + [
            'type' => 'note',
            'project' => $project->id(),
            'sender' => $this->currentUser()->id(),
          ])->save();
        }
        return TRUE;
      }
      catch (EntityStorageException $exception) {
        $this->getLogger('manager')->error('A note could not be saved: @message', ['@message' => $exception->getMessage()]);
        return FALSE;
      }
    });
    if ($saved === NULL) {
      $form_state->set('already_submitted', TRUE);
      return;
    }
    if (!$saved) {
      // Errors cannot be set during submit: rebuild the form with the input.
      // The pane shows the error message when it sees this flag.
      $form_state->set('save_failed', TRUE);
      $form_state->setRebuild();
      return;
    }
    // The pane shows the confirmation when it sees this flag.
    $form_state->set('saved', TRUE);
  }

  /**
   * Gets the submitted date and time as a timestamp.
   */
  protected function occurredTimestamp(FormStateInterface $form_state): ?int {
    $value = $form_state->getValue('occurred');
    return $value instanceof DrupalDateTime && !$value->hasErrors() ? $value->getTimestamp() : NULL;
  }

  /**
   * Gets the site timezone.
   */
  protected function siteTimezone(): string {
    return (string) ($this->config('system.date')->get('timezone.default') ?: date_default_timezone_get());
  }

}
