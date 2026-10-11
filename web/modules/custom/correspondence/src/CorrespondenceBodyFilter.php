<?php

namespace Drupal\correspondence;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

/**
 * Defines how stored mail bodies are filtered on output.
 */
final class CorrespondenceBodyFilter {

  /**
   * The HTML tags that survive when a stored mail body is rendered.
   */
  public const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li'];

  /**
   * Filters a stored mail body for output.
   *
   * The result only contains the allowed tags, balanced, without attributes
   * except for the href of links, which also get rel="noopener noreferrer".
   *
   * @param string $body
   *   The stored HTML body.
   *
   * @return string
   *   The filtered HTML.
   */
  public static function filter(string $body): string {
    $filtered = Xss::filter($body, self::ALLOWED_TAGS);
    // Parsing and serializing closes unclosed elements.
    $document = Html::load($filtered);
    $xpath = new \DOMXPath($document);
    foreach ($xpath->query('//body//*') ?: [] as $element) {
      assert($element instanceof \DOMElement);
      $keep = $element->tagName === 'a' ? $element->getAttribute('href') : NULL;
      while ($element->attributes->length > 0) {
        $element->removeAttributeNode($element->attributes->item(0));
      }
      if ($element->tagName === 'a') {
        if ($keep !== NULL && $keep !== '') {
          $element->setAttribute('href', $keep);
        }
        $element->setAttribute('rel', 'noopener noreferrer');
      }
    }
    // Filter once more, so the result never depends on the serialization.
    return Xss::filter(Html::serialize($document), self::ALLOWED_TAGS);
  }

}
