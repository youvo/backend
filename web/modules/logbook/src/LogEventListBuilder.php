<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the log event entity type.
 */
class LogEventListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {

    $build['table']['#prefix'] = '<div class="system-status-general-info__items clearfix">';
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('log_event');
    foreach ($this->load() as $entity) {
      if ($row = $view_builder->view($entity)) {
        $build['table'][] = $row;
      }
    }
    $build['table']['#suffix'] = '</div>';
    return $build;
  }

}
