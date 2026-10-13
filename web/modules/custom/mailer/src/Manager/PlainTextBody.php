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
    $text = $html;
    $replacements = [
      '#<script\b[^>]*>.*?</script>#is' => '',
      '#<style\b[^>]*>.*?</style>#is' => '',
      '#<br\s*/?>#i' => "\n",
      '#<li\b[^>]*>#i' => '- ',
      '#</li>\s*#i' => "\n",
      '#</p>\s*#i' => "\n\n",
    ];
    foreach ($replacements as $pattern => $replacement) {
      $text = preg_replace($pattern, $replacement, $text) ?? $text;
    }
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
    return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
  }

  /**
   * Converts plain text to safe HTML: everything is escaped, URLs are linked.
   *
   * URLs are delimited on the unescaped text; every segment is escaped on its
   * own, so only the generated p, br and a tags are ever raw HTML.
   */
  public function toHtml(string $text): string {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
      return '';
    }
    $html = '';
    foreach (preg_split("/\n\s*\n/", $text) ?: [$text] as $paragraph) {
      $paragraph = trim($paragraph);
      if ($paragraph === '') {
        continue;
      }
      $segments = preg_split('~(https?://[^\s<>"\']*[^\s<>"\'.,;:!?)\]])~i', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);
      $linked = '';
      foreach ($segments ?: [$paragraph] as $index => $segment) {
        $escaped = htmlspecialchars($segment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Odd indexes are the captured URLs.
        $linked .= $index % 2 === 1 ? '<a href="' . $escaped . '">' . $escaped . '</a>' : $escaped;
      }
      $html .= '<p>' . str_replace("\n", '<br>', $linked) . '</p>';
    }
    return $html;
  }

}
