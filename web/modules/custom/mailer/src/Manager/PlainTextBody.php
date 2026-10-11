<?php

namespace Drupal\mailer\Manager;

/**
 * Converts between the rendered mail body (HTML) and the manager's plain text.
 */
class PlainTextBody {

  /**
   * Converts rendered HTML to plain text.
   */
  public function fromHtml(string $html): string {
    $text = (string) preg_replace('#<br\s*/?>#i', "\n", $html);
    $text = (string) preg_replace('#</p>\s*#i', "\n\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = (string) preg_replace("/[ \t]+\n/", "\n", $text);
    return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
  }

  /**
   * Converts plain text to safe HTML: everything is escaped, URLs are linked.
   */
  public function toHtml(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    $html = '';
    foreach (preg_split("/\n{2,}/", $text) ?: [] as $paragraph) {
      $escaped = htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $linked = (string) preg_replace('~(https?://[^\s<]*[^\s<.,;:!?)])~i', '<a href="$1">$1</a>', $escaped);
      $html .= '<p>' . str_replace("\n", '<br>', $linked) . '</p>';
    }
    return $html;
  }

}
