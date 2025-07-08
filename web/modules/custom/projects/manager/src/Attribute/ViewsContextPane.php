<?php

namespace Drupal\manager\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines the ViewsContextPane plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsContextPane extends Plugin {

  public function __construct(
    string $id,
    public string $label = '',
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
  }

}
