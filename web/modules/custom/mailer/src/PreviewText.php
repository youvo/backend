<?php

namespace Drupal\mailer;

use Drupal\Component\Utility\Unicode;

/**
 * Derives the inbox preview text of a mail from its HTML body.
 *
 * Inboxes show the start of the body next to the subject. Without a preview
 * text this is the greeting, so the preview skips a greeting paragraph and
 * shows what the mail is about.
 */
class PreviewText {

  /**
   * The longest paragraph that counts as a greeting ("Hallo Evi Vogt,").
   */
  protected const GREETING_LENGTH = 60;

  /**
   * Gets the preview text of a mail body.
   *
   * @param string $html
   *   The HTML body.
   * @param int $length
   *   The maximum length of the preview.
   *
   * @return string
   *   The plain preview text, empty if the body has no text.
   */
  public static function fromHtml(string $html, int $length = 110): string {
    $paragraphs = [];
    foreach (preg_split('#</p>|<br\s*/?>#i', $html) ?: [] as $part) {
      $text = html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
      // Separators such as "========" have no letters.
      if (preg_match('/\p{L}/u', $text)) {
        $paragraphs[] = $text;
      }
    }
    if (count($paragraphs) > 1 && str_ends_with($paragraphs[0], ',') && mb_strlen($paragraphs[0]) <= static::GREETING_LENGTH) {
      array_shift($paragraphs);
      // The sentence after the greeting starts in lower case ("vielen Dank").
      $paragraphs[0] = Unicode::ucfirst($paragraphs[0]);
    }
    return Unicode::truncate(implode(' ', $paragraphs), $length, TRUE, TRUE);
  }

}
