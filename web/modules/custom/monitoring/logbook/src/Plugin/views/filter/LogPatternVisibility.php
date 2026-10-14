<?php

namespace Drupal\logbook\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filters the logs by the visibility of their log pattern.
 */
#[ViewsFilter('log_pattern_visibility')]
class LogPatternVisibility extends InOperator implements ContainerFactoryPluginInterface {

  use LogPatternFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(): array {
    $this->valueOptions = $this->matcher->visibilityOptions();
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $selected = array_filter((array) $this->value);
    if ($selected === []) {
      return;
    }
    $ids = [];
    foreach ($selected as $value) {
      $ids = array_merge($ids, $this->matcher->visibilityIds((string) $value));
    }
    $this->restrictTypes(array_values(array_unique($ids)));
  }

}
