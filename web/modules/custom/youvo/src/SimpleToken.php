<?php

namespace Drupal\youvo;

/**
 * Provides a simple token.
 */
class SimpleToken {

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
   * Constructs a SimpleToken object.
   */
  public function __construct(
    protected string $token,
    protected bool $required,
  ) {}

  /**
   * Gets the token.
   */
  public function getToken(): string {
    return $this->token;
  }

  /**
   * Gets the replacement.
   */
  public function getReplacement(): string {
    return $this->replacement;
  }

  /**
   * Checks whether token has a replacement.
   *
   * The replacement for a required token needs to be non-empty. A non-required
   * token can have an empty replacement.
   */
  public function hasReplacement(): bool {
    return isset($this->replacement) &&
      (!$this->isRequired() || !empty($this->getReplacement()));
  }

  /**
   * Sets the replacement.
   */
  public function setReplacement(string $replacement): SimpleToken {
    $this->replacement = $replacement;
    return $this;
  }

  /**
   * Checks whether the token is required.
   */
  public function isRequired(): bool {
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
  public function isProcessed(): bool {
    return $this->processed;
  }

  /**
   * Creates Tokens from a tokens array delivered by the config.
   *
   * @param array $tokens
   *   Tokens provided from configuration.
   *
   * @return \Drupal\youvo\SimpleToken[]
   *   An array of simple tokens.
   */
  public static function createMultiple(array $tokens): array {
    foreach ($tokens as $token) {
      if (isset($token['token']) && isset($token['required'])) {
        $token_objects[] = new SimpleToken(
          $token['token'],
          (bool) $token['required']
        );
      }
    }
    return $token_objects ?? [];
  }

}
