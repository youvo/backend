<?php

namespace Drupal\logbook\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Restricts the logs to enabled, observable log patterns.
 */
#[ViewsFilter('log_pattern_scope')]
class LogPatternScope extends FilterPluginBase implements ContainerFactoryPluginInterface {

  use LogPatternFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return (string) $this->t('Observable');
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->restrictTypes($this->matcher->scopeIds());
  }

}
