<?php

namespace Drupal\questionnaire\Plugin\Field;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\questionnaire\SubmissionManagerInjectionTrait;

/**
 * SubmissionFieldItemList class to generate a computed field.
 */
class SubmissionFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;
  use SubmissionManagerInjectionTrait;

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

        $input = Html::escape($submission->get('value')->value);

        // Explode input for checkboxes and radios.
        if ($question->bundle() == 'checkboxes') {
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
