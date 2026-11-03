<?php

declare(strict_types=1);

namespace Drupal\creatives;

/**
 * Formats the names of the tags creatives describe their abilities with.
 *
 * Tags are free text, so one format keeps "Online Marketing" and
 * "online-marketing" from becoming two tags. Ported from the old platform.
 */
class TagName {

  /**
   * The vocabulary of the tags.
   */
  const string VOCABULARY = 'tags';

  /**
   * Formats a tag name: lowercase words joined by hyphens.
   *
   * Only letters a to z, German umlauts, ß, digits and hyphens remain.
   */
  public static function format(string $name): string {

    $name = mb_strtolower(trim($name));

    // Whitespace between words becomes a hyphen.
    $name = (string) preg_replace('/\s+/u', '-', $name);

    // Other characters are dropped.
    $name = (string) preg_replace('/[^a-z0-9äöüß-]/u', '', $name);

    // Hyphens don't repeat and don't frame the name.
    $name = (string) preg_replace('/-{2,}/', '-', $name);
    return trim($name, '-');
  }

}
