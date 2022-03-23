<?php

namespace Drupal\youvo;

class RestContentShifter {

  /**
   * Pop attributes from request content by type.
   *
   * @param array $content
   * @param string $type
   * @return array
   */
  public static function shiftAttributesByType(array $content, string $type) {
    if (empty($content['data'])) {
      return [];
    }
    $content = array_filter($content['data'], fn ($a) => $a['type'] == $type);
    return array_shift($content)['attributes'];
  }

}
