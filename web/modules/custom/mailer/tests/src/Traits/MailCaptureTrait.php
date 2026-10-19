<?php

namespace Drupal\Tests\mailer\Traits;

use Drupal\symfony_mailer\Component\ComponentMailerInterface;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Captures rendered mails of a component mailer in kernel tests.
 */
trait MailCaptureTrait {

  /**
   * Captured mails, one entry per send.
   *
   * @var array<int, array{to: string[], subject: string, html: string, auto_submitted: ?string}>
   */
  protected array $capturedMails = [];

  /**
   * Registers a post-render callback on the next email of the mailer.
   *
   * Call this before every send that should be captured.
   */
  protected function captureMails(ComponentMailerInterface $mailer): void {
    $mailer->addCallback(function (EmailInterface $email): void {
      $this->capturedMails[] = [
        'to' => array_map(static fn ($a) => $a->getEmail(), $email->getTo()),
        'subject' => (string) $email->getSubject(),
        'html' => (string) $email->getHtmlBody(),
        'auto_submitted' => $email->getHeaders()->get('Auto-Submitted')?->getBodyAsString(),
      ];
    }, EmailInterface::PHASE_POST_RENDER, 950, 'mail_capture');
  }

}
