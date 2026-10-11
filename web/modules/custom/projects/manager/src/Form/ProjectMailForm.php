<?php

namespace Drupal\manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\mailer\Manager\LateTokens;
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
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ManagerMailerInterface::class),
      $container->get('mailer.late_tokens'),
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Only guards against a repeated submitForm() call on this one in-memory
    // form state. Replays of a request are rejected by Drupal's form cache.
    if ($form_state->get('sent')) {
      return;
    }
    $form_state->set('send_failed', FALSE);
    [$project, $subtype] = $form_state->getBuildInfo()['args'];
    $sent = $this->mailer->send($project, $subtype, (string) $form_state->getValue('subject'), (string) $form_state->getValue('body'), $this->currentUser());
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
