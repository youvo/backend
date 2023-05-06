<?php

namespace Drupal\questionnaire;

/**
 * Provides mock injection for submission manager.
 *
 * For the FieldItemList consider:
 *
 * @todo Replace with proper DI after
 *   https://www.drupal.org/project/drupal/issues/2914419 or
 *   https://www.drupal.org/project/drupal/issues/2053415
 */
trait SubmissionManagerInjectionTrait {

  /**
   * The submission manager.
   *
   * @var \Drupal\questionnaire\SubmissionManager
   */
  protected SubmissionManager $submissionManager;

  /**
   * Gets the submission manager.
   *
   * @return \Drupal\questionnaire\SubmissionManager
   *   The submission manager.
   */
  protected function submissionManager() {
    if (!isset($this->submissionManager)) {
      $this->submissionManager = \Drupal::service('submission.manager');
    }
    return $this->submissionManager;
  }

}
