<?php

namespace Drupal\manager\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets a manager add or edit a note on a project.
 *
 * A plain form that is built and processed inside the logbook context pane: it
 * adds a note (arguments: the project) or edits one (arguments: the project and
 * the entry). The pane reads the outcome from the
 * form state flags 'saved', 'save_failed', 'save_denied' and
 * 'already_submitted' and renders the messages itself. Everything is plain
 * text.
 */
class ProjectNoteForm extends FormBase implements TrustedCallbackInterface {

  use NoteFormAuthorizationTrait;

  /**
   * The earliest accepted time (1970-01-02), so the value is positive.
   */
  protected const MIN_TIMESTAMP = 86400;

  /**
   * The name of the request attribute that gets the id of the saved note.
   */
  public const SAVED_ATTRIBUTE = 'manager_pane_saved_note';

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
  public static function trustedCallbacks(): array {
    return ['hideSeconds'];
  }

  /**
   * Cuts the seconds from the value of the time input (date_time_callbacks).
   *
   * The core element always renders HH:MM:SS, which makes some browsers show
   * the seconds even with a one minute step. A posted HH:MM is accepted.
   */
  public static function hideSeconds(array &$element): void {
    if (isset($element['time']['#value']) && is_string($element['time']['#value'])) {
      $element['time']['#value'] = substr($element['time']['#value'], 0, 5);
    }
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
    // The time input shows hours and minutes only, so drop the seconds.
    $occurred->setTime((int) $occurred->format('H'), (int) $occurred->format('i'), 0);

    // The stored values: the script compares the fields with them to see if a
    // note has unsaved changes (and keeps a draft).
    $form['#attributes']['data-initial'] = json_encode([
      'subject' => $correspondence?->get('subject')->value ?? '',
      'body' => $correspondence?->get('body')->value ?? '',
      'date' => $correspondence instanceof CorrespondenceInterface ? $occurred->format('Y-m-d') : '',
      'time' => $correspondence instanceof CorrespondenceInterface ? $occurred->format('H:i') : '',
    ], JSON_THROW_ON_ERROR);

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $correspondence?->get('subject')->value ?? '',
      '#maxlength' => 255,
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('Title')],
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Note'),
      '#default_value' => $correspondence?->get('body')->value ?? '',
      '#rows' => 1,
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('Write a note…')],
    ];
    // The date and time come before the buttons, so the tab order is the
    // order of the fields. In the timeline they sit in the date column.
    $form['occurred'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date and time'),
      '#default_value' => $occurred,
      '#date_timezone' => $timezone,
      // Hours and minutes only: the increment makes the time input step by a
      // minute and the callback keeps the seconds out of its value.
      '#date_increment' => 60,
      '#date_time_callbacks' => [[static::class, 'hideSeconds']],
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    $project_id = $project?->id() ?? $correspondence?->get('project')->target_id;
    if ($project_id !== NULL) {
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $project_id], [
          'query' => ['type' => 'logbook', 'view' => 'index'],
        ]),
        '#attributes' => [
          'data-pane-view' => 'index',
          'data-pane-row' => $correspondence instanceof CorrespondenceInterface ? 'note-' . $correspondence->id() : 'add',
          'class' => ['button'],
        ],
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
    $this->showFieldErrors($form, $form_state);
  }

  /**
   * Shows the error of each invalid field right below it.
   *
   * The pane renders the form without the inline form errors module, so the
   * messages would only reach the status message banner far above the field.
   * This covers the core required errors as well as the ones set here.
   */
  protected function showFieldErrors(array &$form, FormStateInterface $form_state): void {
    $done = [];
    foreach ($form_state->getErrors() as $name => $message) {
      $field = explode('][', (string) $name)[0];
      if (!in_array($field, ['subject', 'body', 'occurred'], TRUE) || isset($done[$field]) || !isset($form[$field])) {
        continue;
      }
      $done[$field] = TRUE;
      $id = 'manager-note-' . $field . '-error';
      $form[$field]['#suffix'] = '<div class="manager-pane-field-error" id="' . $id . '" role="alert">' . Html::escape((string) $message) . '</div>';
      if ($field !== 'occurred') {
        $form[$field]['#attributes']['aria-describedby'] = $id;
      }
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
          $note = $correspondence;
        }
        else {
          $note = $this->entityTypeManager->getStorage('correspondence')->create($values + [
            'type' => 'note',
            'project' => $project->id(),
            'sender' => $this->currentUser()->id(),
          ]);
        }
        $note->save();
        // The pane highlights the saved note in the timeline.
        $this->getRequest()->attributes->set(self::SAVED_ATTRIBUTE, (int) $note->id());
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
