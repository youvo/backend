<?php

namespace Drupal\correspondence;

/**
 * Defines how stored mail bodies are filtered on output.
 */
final class CorrespondenceBodyFilter {

  /**
   * The HTML tags that survive when a stored mail body is rendered.
   */
  public const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li'];

}
