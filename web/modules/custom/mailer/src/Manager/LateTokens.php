<?php

namespace Drupal\mailer\Manager;

/**
 * Resolves the small whitelist of late tokens in manager-edited mail text.
 *
 * Tokens look like [mail:project_link]. They are resolved per recipient at
 * send time on the plain text, before it is converted to HTML. Each subtype
 * allows only its own tokens (see ManagerMailRegistry::lateTokenNames()).
 */
class LateTokens {

  /**
   * Names of the supported tokens.
   */
  public const array NAMES = ['project_link', 'faq_link', 'rules_link', 'new_project_link', 'new_draft_link'];

  /**
   * Gets the literal token text for a name.
   */
  public static function token(string $name): string {
    return '[mail:' . $name . ']';
  }

  /**
   * Gets the token names a mail of the subtype may use.
   *
   * @return string[]
   *   The names.
   */
  public function names(string $subtype): array {
    return array_values(array_intersect(ManagerMailRegistry::lateTokenNames($subtype), static::NAMES));
  }

  /**
   * Replaces tokens in the plain text.
   *
   * Tokens not allowed for the subtype and unknown names are removed.
   *
   * @param string $text
   *   The edited plain text.
   * @param string $subtype
   *   The subtype of the mail.
   * @param array<string, string> $values
   *   Replacement values keyed by token name.
   */
  public function replace(string $text, string $subtype, array $values): string {
    $allowed = $this->names($subtype);
    return (string) preg_replace_callback('/\[mail:([a-z0-9_-]+)\]/i', static function (array $m) use ($values, $allowed): string {
      $name = strtolower($m[1]);
      return in_array($name, $allowed, TRUE) ? (string) ($values[$name] ?? '') : '';
    }, $text);
  }

}
