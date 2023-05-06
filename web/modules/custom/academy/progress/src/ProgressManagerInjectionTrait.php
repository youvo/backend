<?php

namespace Drupal\progress;

/**
 * Provides mock injection for progress manager.
 *
 * For the FieldItemList consider:
 *
 * @todo Replace with proper DI after
 *   https://www.drupal.org/project/drupal/issues/2914419 or
 *   https://www.drupal.org/project/drupal/issues/2053415
 */
trait ProgressManagerInjectionTrait {

  /**
   * The progress manager.
   *
   * @var \Drupal\progress\ProgressManager
   */
  protected ProgressManager $progressManager;

  /**
   * Gets the progress manager.
   *
   * @return \Drupal\progress\ProgressManager
   *   The progress manager.
   */
  protected function progressManager() {
    if (!isset($this->progressManager)) {
      $this->progressManager = \Drupal::service('progress.manager');
    }
    return $this->progressManager;
  }

}
