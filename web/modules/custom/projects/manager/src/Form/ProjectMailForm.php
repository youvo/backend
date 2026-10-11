<?php

namespace Drupal\manager\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\mailer\Manager\LateTokens;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets a manager edit and send a reminder mail for a project.
 *
 * A plain form that is built and processed inside the manager context pane
 * (see ManagerContextPaneMail): the pane reads the outcome from the form
 * state (the 'sent' and 'send_failed' flags) and renders the messages itself.
 */
class ProjectMailForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    protected ManagerMailerInterface $mailer,
    protected LateTokens $lateTokens,
    protected ManagerMailAccess $access,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FormOnceGuard $once,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ManagerMailerInterface::class),
      $container->get('mailer.late_tokens'),
      $container->get('mailer.manager_mail_access'),
      $container->get('entity_type.manager'),
      $container->get('manager.form_once'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'manager_project_mail_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL, string $subtype = ''): array {
    $form['#attributes']['class'][] = 'manager-pane-form';

    // Render the preview once; later builds (rebuilds after a failed send or
    // an error) reuse it.
    if (!$form_state->has('preview')) {
      $preview = $project instanceof ProjectInterface ? $this->mailer->preview($project, $subtype) : NULL;
      $form_state->set('preview', $preview ?? FALSE);
    }
    $preview = $form_state->get('preview');
    if (!$preview) {
      $form['unavailable'] = ['#markup' => '<p>' . $this->t('This mail cannot be sent for this project.') . '</p>'];
      return $form;
    }

    $form['recipients'] = [
      '#type' => 'item',
      '#title' => $this->t('To'),
      '#plain_text' => implode(', ', $preview->recipients),
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $preview->subject,
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#default_value' => $preview->body,
      '#rows' => 14,
      '#required' => TRUE,
    ];
    $form['tokens'] = [
      '#type' => 'item',
      '#title' => $this->t('Placeholders (resolved when sending)'),
      '#markup' => implode(' ', array_map(static fn ($n) => '<code>' . LateTokens::token($n) . '</code>', $this->lateTokens->names())),
      '#description' => $this->t('Plain text. Web addresses become links.'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('manager.context_pane', ['project' => $project->id()], [
        'query' => ['type' => 'mail', 'view' => 'index'],
      ]),
      '#attributes' => ['data-pane-view' => 'index', 'class' => ['button']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->get('preview')) {
      $form_state->setErrorByName('', $this->t('This mail cannot be sent for this project.'));
      return;
    }
    if ($this->authorizedProject($form_state) === NULL) {
      $form_state->setErrorByName('', $this->t('This mail cannot be sent for this project.'));
      return;
    }
    $subject = (string) $form_state->getValue('subject');
    if (preg_match('/[\r\n]/', $subject)) {
      $form_state->setErrorByName('subject', $this->t('The subject must not contain line breaks.'));
    }
    elseif (mb_strlen($subject) > 255) {
      $form_state->setErrorByName('subject', $this->t('The subject is too long.'));
    }
    elseif (trim($subject) === '') {
      $form_state->setErrorByName('subject', $this->t('The subject is required.'));
    }
    if (trim((string) $form_state->getValue('body')) === '') {
      $form_state->setErrorByName('body', $this->t('The message is required.'));
    }
  }

  /**
   * Gets the project to mail if the current user may send this mail now.
   *
   * A POST that carries a form_build_id restores the build info (the project
   * and subtype) of the earlier GET, so neither the serialized project nor
   * the cached subtype can be trusted: the project is reloaded from storage,
   * access is checked again on these actual values, and, if the pane put the
   * project id and subtype of the current request on the request, they must
   * match the cached ones.
   *
   * @return \Drupal\projects\ProjectInterface|null
   *   The freshly loaded project, or NULL if the mail must not be sent.
   */
  protected function authorizedProject(FormStateInterface $form_state): ?ProjectInterface {
    $args = $form_state->getBuildInfo()['args'] ?? [];
    $cached = $args[0] ?? NULL;
    $subtype = $args[1] ?? NULL;
    if (!$cached instanceof ProjectInterface || !is_string($subtype) || $subtype === '') {
      return NULL;
    }
    $expected = $this->getRequest()->attributes->get('manager_pane_mail');
    if (is_array($expected) && ($expected !== [(string) $cached->id(), $subtype])) {
      return NULL;
    }
    $project = $this->entityTypeManager->getStorage('project')->load($cached->id());
    if (!$project instanceof ProjectInterface) {
      return NULL;
    }
    if (!$this->access->access($this->currentUser(), $project, $subtype)->isAllowed()) {
      return NULL;
    }
    return $project;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Guard 1: only covers a repeated submitForm() call on this one in-memory
    // form state.
    if ($form_state->get('sent')) {
      return;
    }
    $form_state->set('send_failed', FALSE);
    // Never trust the restored build info: check access again on the actual
    // project and subtype, fail closed.
    $project = $this->authorizedProject($form_state);
    if ($project === NULL) {
      $form_state->set('send_denied', TRUE);
      return;
    }
    $subtype = $form_state->getBuildInfo()['args'][1];
    // Guard 2: a replayed POST (same form_build_id, still a valid token)
    // rebuilds the form and would send again; a POST without a usable
    // form_build_id is refused as well (fail closed). The build id is claimed
    // before sending and released again if the send fails or throws.
    $sent = $this->once->runOnce($form_state, fn (): bool => $this->mailer->send($project, $subtype, (string) $form_state->getValue('subject'), (string) $form_state->getValue('body'), $this->currentUser()));
    if ($sent === NULL) {
      $form_state->set('already_submitted', TRUE);
      return;
    }
    if (!$sent) {
      // Errors cannot be set during submit: rebuild the form with the input.
      // The pane shows the error message when it sees this flag.
      $form_state->set('send_failed', TRUE);
      $form_state->setRebuild();
      return;
    }
    // The pane shows the confirmation when it sees this flag.
    $form_state->set('sent', TRUE);
  }

}
