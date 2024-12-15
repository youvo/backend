<?php

namespace Drupal\questionnaire;

/**
 * Provides mock injection for submission manager.
 *
 * For the FieldItemList consider:
 *
 * @todo Use DI after https://www.drupal.org/project/drupal/issues/3294266
 */
trait SubmissionManagerInjectionTrait {

  /**
   * The submission manager.
   */
  protected SubmissionManager $submissionManager;

  /**
   * Gets the submission manager.
   */
  protected function submissionManager(): SubmissionManager {
    if (!isset($this->submissionManager)) {
      $this->submissionManager = \Drupal::service('submission.manager');
    }
    return $this->submissionManager;
  }

}
