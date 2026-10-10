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
   * Sends the email and records it as correspondence.
   *
   * Never throws: a failed send returns FALSE. Without a recipient address
   * nothing is sent or recorded and FALSE is returned. Nothing is recorded when
   * nothing was rendered, which is the case when the mail was skipped.
   */
  protected function deliver(EmailInterface $email, ?ProjectInterface $project, string $subtype, ?string $to): bool {
    if ($to === NULL || $to === '') {
      $this->logger->error('Unable to send mail %tag for project @project: no recipient address.', [
        '%tag' => $this->getBaseTag() . '.' . $subtype,
        '@project' => $project?->id() ?? '-',
      ]);
      return FALSE;
    }
    $captured = ['subject' => '', 'body' => ''];
    $email->addCallback(static function (EmailInterface $e) use (&$captured): void {
      $captured['subject'] = (string) $e->getSubject();
      $captured['body'] = (string) $e->getHtmlBody();
    }, EmailInterface::PHASE_POST_RENDER, 960, 'correspondence_capture');

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
      $this->recorder->recordMail($project, $this->getBaseTag() . '.' . $subtype, [$to], $captured['subject'], $captured['body'], $success);
    }
    return $success;
  }

}
