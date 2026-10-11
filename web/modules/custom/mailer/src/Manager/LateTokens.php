<?php

namespace Drupal\mailer\Manager;

/**
 * Resolves the small whitelist of late tokens in manager-edited mail text.
 *
 * Tokens look like [mail:project_link]. They are resolved per recipient at
 * send time on the plain text, before it is converted to HTML.
 */
class LateTokens {

  /**
   * Names of the supported tokens.
   */
  public const NAMES = ['project_link'];

  /**
   * Gets the literal token text for a name.
   */
  public static function token(string $name): string {
    return '[mail:' . $name . ']';
  }

  /**
   * Gets the supported token names.
   *
   * @return string[]
   *   The names.
   */
  public function names(): array {
    return self::NAMES;
  }

  /**
   * Replaces tokens in the plain text; unknown names are removed.
   *
   * @param string $text
   *   The edited plain text.
   * @param array<string, string> $values
   *   Replacement values keyed by token name.
   */
  public function replace(string $text, array $values): string {
    return (string) preg_replace_callback('/\[mail:([a-z_]+)\]/i', static function (array $m) use ($values): string {
      $name = strtolower($m[1]);
      return in_array($name, self::NAMES, TRUE) ? (string) ($values[$name] ?? '') : '';
    }, $text);
  }

}
