<?php

namespace Drupal\manager\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\manager\Ajax\ManagerRefreshCommand;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets a manager add or edit a note on a project.
 *
 * The mode is determined by the route parameter: a project adds a note, a
 * correspondence entry edits it. Everything is plain text.
 */
class ProjectNoteForm extends FormBase {

  /**
   * The id of the wrapper that the ajax callback replaces.
   */
  protected const WRAPPER_ID = 'manager-note-form-wrapper';

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
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('mailer.manager_mail_access'),
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
    $form['#prefix'] = '<div id="' . self::WRAPPER_ID . '">';
    $form['#suffix'] = '</div>';
    $form['status_messages'] = ['#type' => 'status_messages'];

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
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => self::WRAPPER_ID,
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
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
    if ($form_state->get('saved')) {
      return;
    }
    $form_state->set('save_failed', FALSE);
    [$project, $correspondence] = $this->arguments($form_state);
    $saved = FALSE;
    $values = [
      'subject' => trim((string) $form_state->getValue('subject')),
      'body' => ['value' => (string) $form_state->getValue('body'), 'format' => 'plain_text'],
      'occurred' => $this->occurredTimestamp($form_state),
    ];
    try {
      if ($correspondence instanceof CorrespondenceInterface) {
        // A note can only be edited together with its own project.
        $matches = !$project instanceof ProjectInterface
          || (string) $correspondence->get('project')->target_id === (string) $project->id();
        if ($matches && $correspondence->isNote() && $correspondence->access('update', $this->currentUser())) {
          foreach ($values as $name => $value) {
            $correspondence->set($name, $value);
          }
          $correspondence->save();
          $saved = TRUE;
        }
      }
      elseif ($project instanceof ProjectInterface) {
        // The create access is permission-based, so check the project here.
        if (
          $this->entityTypeManager->getAccessControlHandler('correspondence')->createAccess('note', $this->currentUser())
          && $this->access->noteAccess($this->currentUser(), $project)->isAllowed()
        ) {
          $this->entityTypeManager->getStorage('correspondence')->create($values + [
            'type' => 'note',
            'project' => $project->id(),
            'sender' => $this->currentUser()->id(),
          ])->save();
          $saved = TRUE;
        }
      }
    }
    catch (EntityStorageException $exception) {
      $this->getLogger('manager')->error('A note could not be saved: @message', ['@message' => $exception->getMessage()]);
    }
    if (!$saved) {
      // Errors cannot be set during submit: rebuild the form with the input.
      $form_state->set('save_failed', TRUE);
      $form_state->setRebuild();
      $this->messenger()->addError($this->t('The note could not be saved.'));
      return;
    }
    $form_state->set('saved', TRUE);
    // The ajax response closes the dialog and refreshes the pane instead.
    $request = $this->getRequest();
    if (!$request->query->has('ajax_form') && !$request->request->has('_drupal_ajax')) {
      $this->messenger()->addStatus($this->t('The note was saved.'));
    }
  }

  /**
   * Ajax callback: closes the dialog on success, shows errors otherwise.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->get('save_failed') || $form_state->hasAnyErrors()) {
      return $response->addCommand(new ReplaceCommand('#' . self::WRAPPER_ID, $form));
    }
    [$project, $correspondence] = $this->arguments($form_state);
    $project_id = $correspondence instanceof CorrespondenceInterface
      ? $correspondence->get('project')->target_id
      : $project?->id();
    return $response
      ->addCommand(new CloseModalDialogCommand())
      ->addCommand(new ManagerRefreshCommand($project_id));
  }

  /**
   * Gets the project and the entry from the build arguments.
   *
   * @return array{0: \Drupal\projects\ProjectInterface|null, 1: \Drupal\correspondence\CorrespondenceInterface|null}
   *   The project (add mode) and the entry (edit mode).
   */
  protected function arguments(FormStateInterface $form_state): array {
    $args = $form_state->getBuildInfo()['args'] ?? [];
    $project = $args[0] ?? NULL;
    $correspondence = $args[1] ?? NULL;
    return [
      $project instanceof ProjectInterface ? $project : NULL,
      $correspondence instanceof CorrespondenceInterface ? $correspondence : NULL,
    ];
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
