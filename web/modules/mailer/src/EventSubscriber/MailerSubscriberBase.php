<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mailer\Entity\TransactionalEmail;
use Drupal\mailer\MailerTokenReplacer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a base class for mailer subscribers.
 */
abstract class MailerSubscriberBase implements EventSubscriberInterface {

  /**
   * Constructs a MailerSubscriberBase object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The mailer logger channel.
   * @param \Drupal\mailer\MailerTokenReplacer $mailerTokenReplacer
   *   The mailer token replacer service.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected MailerTokenReplacer $mailerTokenReplacer
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
   * @param \Drupal\mailer\MailerToken[] $tokens
   *   The tokens.
   *
   * @return string
   *   The text with replaced tokens.
   */
  protected function handleTokens(string $text, array $replacements, array $tokens): string {
    $this->mailerTokenReplacer->populateReplacements($replacements, $tokens);
    $this->mailerTokenReplacer->replace($text, $tokens);
    $this->mailerTokenReplacer->validate($tokens);
    return $text;
  }

}
