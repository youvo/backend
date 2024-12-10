<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mailer\Entity\TransactionalEmail;
use Drupal\youvo\SimpleTokenReplacer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a base class for mailer subscribers.
 */
abstract class MailerSubscriberBase implements EventSubscriberInterface {

  const EMAIL_ID = NULL;

  /**
   * Constructs a MailerSubscriberBase object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The mailer logger channel.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager service.
   * @param \Drupal\youvo\SimpleTokenReplacer $simpleTokenReplacer
   *   The mailer token replacer service.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected MailManagerInterface $mailManager,
    protected SimpleTokenReplacer $simpleTokenReplacer,
  ) {}

  /**
   * Gets the transactional email configuration entity.
   */
  protected function loadTransactionalEmail(string $email_id): ?TransactionalEmail {
    try {
      /** @var \Drupal\mailer\Entity\TransactionalEmail|null $transactional_email */
      $transactional_email = $this->entityTypeManager
        ->getStorage('transactional_email')->load($email_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $transactional_email = NULL;
    }
    if (empty($transactional_email)) {
      $this->logger->error('Unable to load transactional email entity (%id).', ['%id' => $email_id]);
    }
    return $transactional_email;
  }

  /**
   * Handles tokens.
   *
   * @param string $text
   *   A text with tokens to replace.
   * @param array $replacements
   *   An array containing the replacements for the tokens.
   * @param \Drupal\youvo\SimpleToken[] $tokens
   *   The tokens.
   * @param bool $validate
   *   Whether the tokens should be validated. Defaults to TRUE.
   *
   * @return string
   *   The text with replaced tokens.
   */
  protected function handleTokens(string $text, array $replacements, array $tokens, bool $validate = TRUE): string {
    $this->simpleTokenReplacer->populateReplacements($replacements, $tokens);
    $this->simpleTokenReplacer->replace($text, $tokens);
    if ($validate) {
      $this->simpleTokenReplacer->validate($tokens);
    }
    return $text;
  }

  /**
   * Handles tokens for subject.
   *
   * @param \Drupal\mailer\Entity\TransactionalEmail $email
   *   The transactional email.
   * @param array $replacements
   *   An array containing the replacements for the tokens.
   *
   * @return string
   *   The subject with replaced tokens.
   */
  protected function handleTokensSubject(TransactionalEmail $email, array $replacements): string {
    return $this->handleTokens($email->subject(), $replacements, $email->tokens(), FALSE);
  }

  /**
   * Handles tokens for body.
   *
   * @param \Drupal\mailer\Entity\TransactionalEmail $email
   *   The transactional email.
   * @param array $replacements
   *   An array containing the replacements for the tokens.
   *
   * @return string
   *   The body with replaced tokens.
   */
  protected function handleTokensBody(TransactionalEmail $email, array $replacements): string {
    return $this->handleTokens($email->body(), $replacements, $email->tokens());
  }

  /**
   * Sends email with the mail manager.
   */
  protected function sendMail(string $to, string $subject, string $body, ?string $reply = NULL, string $langcode = 'de'): void {
    $this->mailManager->mail(
      'mailer',
      static::EMAIL_ID ?? 'generic',
      $to,
      $langcode,
      [
        '_subject' => $subject,
        '_body' => $body,
        '_error_message' => FALSE,
      ],
      $reply ?? $this->getSiteMail()
    );
  }

  /**
   * Gets the site email.
   */
  protected function getSiteMail() {
    return $this->configFactory->get('system.site')->get('mail');
  }

}
