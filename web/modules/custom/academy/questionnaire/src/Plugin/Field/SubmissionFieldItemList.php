<?php

namespace Drupal\questionnaire\Plugin\Field;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\questionnaire\SubmissionManagerInjectionTrait;

/**
 * SubmissionFieldItemList class to generate a computed field.
 */
class SubmissionFieldItemList extends FieldItemList {

  use ComputedItemListTrait;
  use SubmissionManagerInjectionTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue(): void {

    // Populate list if it was not calculated yet.
    if (empty($this->list)) {

      // Get question and respective submission.
      /** @var \Drupal\questionnaire\Entity\Question $question */
      $question = $this->getEntity();
      $submission = $this->submissionManager()->getSubmission($question);

      // If submission is found, calculate list.
      if ($submission !== NULL) {

        $input = Html::escape($submission->get('value')->value);

        // Explode input for checkboxes and radios.
        if ($question->bundle() === 'checkboxes' || $question->bundle() === 'task') {
          $values = explode(',', $input);
          foreach ($values as $value) {
            /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem $item */
            $item = $this->createItem(0, $value);
            $item->getValueProperty()->mergeCacheMaxAge(0);
            $this->list[] = $item;
          }
        }
        // Append list with input for other types.
        else {
          /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem $item */
          $item = $this->createItem(0, $input);
          $item->getValueProperty()->mergeCacheMaxAge(0);
          $this->list[0] = $item;
        }
      }

      // If there is no submission, create empty item with attached cache info.
      // @see \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem
      else {
        /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem $item */
        $item = $this->createItem(0, "");
        $item->getValueProperty()->mergeCacheMaxAge(0);
        $this->list[0] = $item;
      }
    }
  }

}
