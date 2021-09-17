<?php

namespace Drupal\quizzes\Entity;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Implements quiz specific methods.
 */
class Quiz extends Paragraph {

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (!$this->isNew()) {
      // Remove question references in quiz paragraph entity.
      /** @var \Drupal\quizzes\Entity\Question[] $questions */
      $questions_reference = $this->get('questions')->getValue();
      $question_ids = array_column($questions_reference, 'target_id');
      $questions = \Drupal::entityTypeManager()
        ->getStorage('question')
        ->loadMultiple($question_ids);
      foreach ($questions as $question) {
        $question->delete();
      }
    }
    parent::delete();
  }

}
