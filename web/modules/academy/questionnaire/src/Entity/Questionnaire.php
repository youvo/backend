<?php

namespace Drupal\questionnaire\Entity;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Implements questionnaire specific methods.
 */
class Questionnaire extends Paragraph {

  /**
   * Get questions.
   *
   * @return \Drupal\questionnaire\Entity\Question[]
   *   The referenced questions.
   */
  public function getQuestions() {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $questions_field */
    $questions_field = $this->get('questions');
    return $questions_field->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (!$this->isNew()) {
      // Remove question references in questionnaire paragraph entity.
      $questions = $this->getQuestions();
      foreach ($questions as $question) {
        $question->delete();
      }
    }
    parent::delete();
  }

}
