<?php

namespace Drupal\mailer;

/**
 * Provides a mailer token.
 */
class MailerToken {

  /**
   * Indicates whether the token was processed.
   *
   * @var bool
   */
  protected bool $processed = FALSE;

  /**
   * The replacement for the token.
   *
   * @var string
   */
  protected string $replacement;

  /**
   * Constructs a MailerToken object.
   */
  public function __construct(
    protected string $token,
    protected bool $required
  ) {}

  /**
   * Gets the token.
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * Gets the replacement.
   */
  public function getReplacement() {
    return $this->replacement;
  }

  /**
   * Checks whether token has a replacement.
   */
  public function hasReplacement() {
    return !empty($this->getReplacement());
  }

  /**
   * Sets the replacement.
   */
  public function setReplacement(string $replacement) {
    $this->replacement = $replacement;
    return $this;
  }

  /**
   * Checks whether the token is required.
   */
  public function isRequired() {
    return $this->required;
  }

  /**
   * Checks whether a text contains this token.
   */
  public function isContainedIn(string $text): bool {
    return str_contains($text, $this->getToken());
  }

  /**
   * Processes a text.
   *
   * Checks whether the token has a replacement and whether a required token
   * is contained in the text before processing.
   */
  public function processText(string &$text): void {
    if ($this->hasReplacement() && (str_contains($text, $this->getToken()) || !$this->isRequired())) {
      $text = str_replace($this->getToken(), $this->getReplacement(), $text);
      $this->processed = TRUE;
    }
  }

  /**
   * Checks whether the token was processed.
   */
  public function isProcessed() {
    return $this->processed;
  }

  /**
   * Creates Tokens from a tokens array delivered by the config.
   *
   * @param array $tokens
   *   Tokens provided from configuration.
   *
   * @return \Drupal\mailer\MailerToken[]
   *   An array of mailer tokens.
   */
  public static function createMultiple(array $tokens) {
    foreach ($tokens as $token) {
      if (isset($token['token']) && isset($token['required'])) {
        $token_objects[] = new MailerToken(
          $token['token'],
          (bool) $token['required']
        );
      }
    }
    return $token_objects ?? [];
  }

}
