<?php

namespace Drupal\mailer\Mailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\mailer\CorrespondenceRecorder;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Component\ComponentMailerBase;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class for the youvo mailers.
 *
 * Subclasses that override build() must call parent::build(), which sets the
 * reply-to address: the manager of the project, or the site mail address. An
 * exception during build or render is logged and makes deliver() return FALSE,
 * but is not recorded as correspondence because nothing was rendered.
 */
abstract class YouvoMailerBase extends ComponentMailerBase {

  /**
   * Constructs a youvo mailer.
   */
  public function __construct(
    MailerPlusInterface $mailer,
    MailerLookupInterface $mailerLookup,
    string $baseTag,
    protected CorrespondenceRecorder $recorder,
    protected LoggerInterface $logger,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($mailer, $mailerLookup, $baseTag);
  }

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    // Do not send mails while the site is being installed (dummy content).
    if (InstallerKernel::installationAttempted()) {
      throw new SkipMailException('Installer is running.');
    }
  }

  /**
   * {@inheritdoc}
   *
   * Sets the manager of the project as reply-to address when there is one and
   * the site mail address otherwise, as the replaced mail code did.
   * Addresses other than the recipient can only be set during the build phase.
   */
  public function build(EmailInterface $email): void {
    $project = $email->getParam('project');
    $owner = $project instanceof ProjectInterface ? $project->getOwner() : NULL;
    $manager = $owner instanceof Organization ? $owner->getManager() : NULL;
    $reply_to = $manager?->getEmail() ?: (string) $this->configFactory->get('system.site')->get('mail');
    if ($reply_to !== '') {
      $email->setReplyTo($reply_to);
    }
  }

  /**
   * Gets the mail address of the owner of a project.
   *
   * Returns NULL instead of throwing when the project has no usable owner, so
   * that deliver() can refuse the missing recipient.
   */
  protected function ownerEmail(ProjectInterface $project): ?string {
    try {
      $owner = $project->getOwner();
    }
    catch (\Throwable) {
      return NULL;
    }
    return $owner->getEmail() ?: NULL;
  }

  /**
   * Sends the email and records it as correspondence.
   *
   * Never throws: a failed send returns FALSE. Without a recipient address
   * nothing is sent or recorded and FALSE is returned. Nothing is recorded when
   * nothing was rendered, which is the case when the mail was skipped.
   *
   * The sent mail keeps the secrets (such as one-time login links); they are
   * redacted from the subject and body that are recorded as correspondence.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email.
   * @param \Drupal\projects\ProjectInterface|null $project
   *   The project the correspondence belongs to.
   * @param string $subtype
   *   The subtype without the base tag.
   * @param string|null $to
   *   The recipient address.
   * @param string[] $secrets
   *   Strings that must not be stored.
   * @param string $origin
   *   The origin of the mail as recorded: 'automatic' or 'manager'.
   * @param int|null $sender
   *   The uid of the user who sent the mail, if a person did.
   *
   * @return bool
   *   TRUE if the mail was sent.
   */
  protected function deliver(EmailInterface $email, ?ProjectInterface $project, string $subtype, ?string $to, array $secrets = [], string $origin = 'automatic', ?int $sender = NULL): bool {
    if ($to === NULL || $to === '') {
      $this->logger->error('Unable to send mail %tag for project @project: no recipient address.', [
        '%tag' => $this->getBaseTag() . '.' . $subtype,
        '@project' => $project?->id() ?? '-',
      ]);
      return FALSE;
    }
    $captured = ['subject' => '', 'body' => ''];
    // Capture after the URLs are made absolute (700) and before the layout
    // wraps the body (800): the record holds the text without the layout.
    $email->addCallback(static function (EmailInterface $e) use (&$captured): void {
      $captured['subject'] = (string) $e->getSubject();
      $captured['body'] = (string) $e->getHtmlBody();
    }, EmailInterface::PHASE_POST_RENDER, 750, 'correspondence_capture');

    try {
      $success = $email->setTo($to)->send();
    }
    catch (\Throwable $e) {
      $this->logger->error('Unable to send mail %tag: @message', [
        '%tag' => $this->getBaseTag() . '.' . $subtype,
        '@message' => $e->getMessage(),
      ]);
      $success = FALSE;
    }

    if ($captured['subject'] !== '' || $captured['body'] !== '') {
      $this->recorder->recordMail($project, $this->getBaseTag() . '.' . $subtype, [$to], self::redactSecrets($captured['subject'], $secrets), self::redactSecrets($captured['body'], $secrets), $success, $origin, $sender);
    }
    return $success;
  }

  /**
   * Replaces secrets in rendered text by a visible marker.
   *
   * Each secret is replaced in its plain, HTML-escaped and URL-encoded form,
   * because the body is rendered HTML. Empty secrets are ignored.
   *
   * @param string $text
   *   The rendered subject or body.
   * @param string[] $secrets
   *   The secrets to remove.
   */
  public static function redactSecrets(string $text, array $secrets): string {
    $forms = [];
    foreach ($secrets as $secret) {
      if ($secret === '') {
        continue;
      }
      array_push(
        $forms,
        $secret,
        htmlspecialchars($secret),
        htmlspecialchars($secret, ENT_QUOTES | ENT_SUBSTITUTE),
        htmlspecialchars($secret, ENT_QUOTES | ENT_HTML5),
        rawurlencode($secret),
      );
    }
    // Longest first, so that no form is broken by a shorter one.
    $forms = array_unique($forms);
    usort($forms, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    return $forms === [] ? $text : str_replace($forms, '[Link entfernt]', $text);
  }

}
