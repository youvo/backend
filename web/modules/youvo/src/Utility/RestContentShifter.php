<?php

namespace Drupal\youvo\Utility;

/**
 * Helper to shift arrays from REST requests.
 */
class RestContentShifter {

  /**
   * Pops attributes from request content by type.
   */
  public static function shiftAttributesByType(array $content, string $type) {
    if (empty($content['data'])) {
      return [];
    }
    $content = array_filter($content['data'], fn ($a) => $a['type'] == $type);
    return array_shift($content)['attributes'];
  }

}
