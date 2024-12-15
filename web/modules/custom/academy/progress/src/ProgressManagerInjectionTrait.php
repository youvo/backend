<?php

namespace Drupal\progress;

/**
 * Provides mock injection for progress manager.
 *
 * For the FieldItemList consider:
 *
 * @todo Use DI after https://www.drupal.org/project/drupal/issues/3294266
 */
trait ProgressManagerInjectionTrait {

  /**
   * The progress manager.
   */
  protected ProgressManager $progressManager;

  /**
   * Gets the progress manager.
   */
  protected function progressManager(): ProgressManager {
    if (!isset($this->progressManager)) {
      $this->progressManager = \Drupal::service('progress.manager');
    }
    return $this->progressManager;
  }

}
