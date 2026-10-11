<?php

namespace Drupal\manager\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\mailer\Manager\LateTokens;
use Drupal\manager\Ajax\ManagerRefreshCommand;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets a manager edit and send a reminder mail for a project.
 */
class ProjectMailForm extends FormBase {

  /**
   * The id of the wrapper that the ajax callback replaces.
   */
  protected const WRAPPER_ID = 'manager-mail-form-wrapper';

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
    $form['#prefix'] = '<div id="' . self::WRAPPER_ID . '">';
    $form['#suffix'] = '</div>';
    $form['status_messages'] = ['#type' => 'status_messages'];

    // Render the preview once; later builds (ajax rebuilds) reuse it.
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
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => self::WRAPPER_ID,
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * The send happens here, after the field checks, because Drupal does not
   * allow errors to be set in the submit handler and a failed send must keep
   * the dialog open with the text intact.
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
    if ($form_state->hasAnyErrors()) {
      return;
    }

    [$project, $subtype] = $form_state->getBuildInfo()['args'];
    $sent = $this->mailer->send($project, $subtype, $subject, (string) $form_state->getValue('body'), $this->currentUser());
    if (!$sent) {
      $form_state->setErrorByName('', $this->t('The mail could not be sent. It was recorded as failed; please try again later.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // The mail was sent in validateForm(); nothing is left to do.
  }

  /**
   * Ajax callback: closes the dialog on success, shows errors otherwise.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      return $response->addCommand(new ReplaceCommand('#' . self::WRAPPER_ID, $form));
    }
    [$project] = $form_state->getBuildInfo()['args'];
    return $response
      ->addCommand(new CloseModalDialogCommand())
      ->addCommand(new ManagerRefreshCommand($project->id()));
  }

}
