<?php

declare(strict_types=1);

namespace Drupal\Tests\youvo\Traits;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Drupal\symfony_mailer\Processor\CallbackEmailProcessor;

/**
 * Intercepts every mail of the test process without changing config.
 *
 * Adds a processor to the mailer service that records each rendered mail and
 * sends it to a test transport instead of the site transport. Requests made
 * with RequestTrait run in the same process and are intercepted as well.
 */
trait MailInterceptTrait {

  /**
   * The intercepted mails, one entry per mail.
   *
   * @var array<int, array{tag: string, to: string[], reply_to: string[], subject: string, html: string, auto_submitted: ?string}>
   */
  protected array $interceptedMails = [];

  /**
   * The transport DSN the intercepted mails are sent to.
   */
  protected string $mailTransportDsn = 'null://null';

  /**
   * Starts intercepting mails.
   */
  protected function interceptMails(): void {
    $this->container->get(MailerPlusInterface::class)->addProcessor(new CallbackEmailProcessor(
      weight: 10000,
      id: 'test_mail_intercept',
      postRender: function (EmailInterface $email): void {
        $this->interceptedMails[] = [
          'tag' => $email->getTag(),
          'to' => array_map(static fn ($address) => $address->getEmail(), $email->getTo()),
          'reply_to' => array_map(static fn ($address) => $address->getEmail(), $email->getReplyTo()),
          'subject' => (string) $email->getSubject(),
          'html' => (string) $email->getHtmlBody(),
          'auto_submitted' => $email->getHeaders()->get('Auto-Submitted')?->getBodyAsString(),
        ];
        $email->setTransport($this->mailTransportDsn);
      },
    ));
  }

  /**
   * Makes the following sends fail.
   */
  protected function failMails(): void {
    $this->mailTransportDsn = 'smtp://127.0.0.1:1';
  }

}
