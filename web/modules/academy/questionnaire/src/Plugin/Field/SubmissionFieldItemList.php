<?php

namespace Drupal\questionnaire\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * SubmissionFieldItemList class to generate a computed field.
 */
class SubmissionFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * The submission manager.
   *
   * @var \Drupal\questionnaire\SubmissionManager
   */
  protected $submissionManager;

  /**
   * Gets the submission manager.
   *
   * @return \Drupal\questionnaire\SubmissionManager
   *   The submission manager.
   */
  protected function submissionManager() {
    if (!$this->submissionManager) {
      $this->submissionManager = \Drupal::service('submission.manager');
    }
    return $this->submissionManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {

    // Populate list if it was not calculated yet.
    if (empty($this->list)) {

      // Get question and respective submission.
      $question = $this->getEntity();
      $submission = $this->submissionManager()->getSubmission($question);

      // If submission is found, calculate list.
      if (!empty($submission)) {

        $input = $submission->get('value')->value;

        // Explode input for checkboxes and radios.
        if ($question->bundle() == 'checkboxes' ||
          $question->bundle() == 'radios') {
          $values = explode(',', $input);
          foreach ($values as $value) {
            $item = $this->createItem(0, $value);
            $item->get('value')->mergeCacheMaxAge(0);
            $this->list[] = $item;
          }
        }
        // Append list with input for other types.
        else {
          $item = $this->createItem(0, $input);
          $item->get('value')->mergeCacheMaxAge(0);
          $this->list[0] = $item;
        }
      }
      // If there is no submission, create empty item with attached cache info.
      // @see \Drupal\academy\Plugin\Field\FieldType\CacheableStringItem
      else {
        $item = $this->createItem(0, "");
        $item->get('value')->mergeCacheMaxAge(0);
        $this->list[0] = $item;
      }
    }
  }

}
