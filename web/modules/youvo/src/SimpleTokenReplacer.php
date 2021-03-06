<?php

namespace Drupal\youvo;

use Psr\Log\LoggerInterface;

/**
 * Provides service methods to replace tokens in a text.
 */
class SimpleTokenReplacer {

  /**
   * Constructs a SimpleTokenReplacer service.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(protected LoggerInterface $logger) {}

  /**
   * Populates tokens with replacements.
   *
   * @param array $replacements
   *   The replacements.
   * @param \Drupal\youvo\SimpleToken[] $tokens
   *   The tokens.
   */
  public function populateReplacements(array $replacements, array $tokens): void {
    foreach ($tokens as $token) {
      if (array_key_exists($token->getToken(), $replacements)) {
        $token->setReplacement($replacements[$token->getToken()]);
      }
    }
  }

  /**
   * Replaces tokens in a text.
   *
   * @param string $text
   *   The text containing unresolved tokens.
   * @param \Drupal\youvo\SimpleToken[] $tokens
   *   The tokens.
   */
  public function replace(string &$text, array $tokens): void {
    foreach ($tokens as $token) {
      $token->processText($text);
    }
  }

  /**
   * Validates tokens being processed.
   *
   * @param \Drupal\youvo\SimpleToken[] $tokens
   *   The processed tokens.
   */
  public function validate(array $tokens): void {
    foreach ($tokens as $token) {
      if (!$token->isProcessed()) {
        $this->logger->error('The token %token was not processed.', ['%token' => $token->getToken()]);
      }
    }
  }

}
