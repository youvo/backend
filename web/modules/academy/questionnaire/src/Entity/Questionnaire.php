<?php

namespace Drupal\questionnaire\Entity;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Implements questionnaire specific methods.
 */
class Questionnaire extends Paragraph {

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (!$this->isNew()) {
      // Remove question references in questionnaire paragraph entity.
      /** @var \Drupal\questionnaire\Entity\Question[] $questions */
      $questions_reference = $this->get('questions')->getValue();
      $question_ids = array_column($questions_reference, 'target_id');
      $questions = $this->entityTypeManager()
        ->getStorage('question')
        ->loadMultiple($question_ids);
      foreach ($questions as $question) {
        $question->delete();
      }
    }
    parent::delete();
  }

}
