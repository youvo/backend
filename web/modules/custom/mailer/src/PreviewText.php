<?php

namespace Drupal\mailer;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;

/**
 * Derives the inbox preview text of a mail from its HTML body.
 *
 * Skips a leading greeting, which inboxes would otherwise show next to the
 * subject.
 */
class PreviewText {

  /**
   * The longest paragraph that counts as a greeting ("Hallo Evi Vogt,").
   */
  protected const GREETING_LENGTH = 80;

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
    // Block elements end a paragraph; <br> only separates words, so a free
    // text stays one paragraph.
    foreach (preg_split('#</(?:p|div|li|h[1-6]|td|tr|blockquote)>#i', $html) ?: [] as $part) {
      $part = preg_replace('#<br\s*/?>#i', ' ', $part) ?? $part;
      $text = Html::decodeEntities(strip_tags($part));
      $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
      // Separators such as "========" have no letters.
      if (preg_match('/\p{L}/u', $text)) {
        $paragraphs[] = $text;
      }
    }
    if (count($paragraphs) > 1 && str_ends_with($paragraphs[0], ',') && mb_strlen($paragraphs[0]) <= static::GREETING_LENGTH) {
      array_shift($paragraphs);
      // The text after a greeting starts in lower case.
      $paragraphs[0] = Unicode::ucfirst($paragraphs[0]);
    }
    return Unicode::truncate(implode(' ', $paragraphs), $length, TRUE, TRUE);
  }

}
