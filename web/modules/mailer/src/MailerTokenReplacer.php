<?php

namespace Drupal\mailer;

use Psr\Log\LoggerInterface;

/**
 * Provides service methods to replace tokens in a text.
 */
class MailerTokenReplacer {

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a MailerTokenReplacer service.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Replaces tokens in a text.
   *
   * @param string $text
   *   The text containing unresolved tokens.
   * @param \Drupal\mailer\MailerToken[] $tokens
   *   The tokens.
   */
  public function replace(string &$text, array $tokens): void {
    foreach ($tokens as $token) {
      if (!$token instanceof MailerToken) {
        throw new \InvalidArgumentException('Provided token is not of type MailerToken.');
      }
      $token->processText($text);
    }
  }

  /**
   * Validates token replacement after processing.
   *
   * @param string $text
   *   The processed text.
   * @param \Drupal\mailer\MailerToken[] $tokens
   *   The processed tokens.
   */
  public function validate(string $text, array $tokens): void {
    foreach ($tokens as $token) {
      if (!$token instanceof MailerToken) {
        throw new \InvalidArgumentException('Provided token is not of type MailerToken.');
      }
      if (!$token->isProcessed()) {
        $this->logger->error('The token %token was not processed.', ['%token' => $token->getToken()]);
      }
      if (str_contains($text, $token->getToken())) {
        $this->logger->error('The token %token was not replaced.', ['%token' => $token->getToken()]);
      }
    }
  }

}
