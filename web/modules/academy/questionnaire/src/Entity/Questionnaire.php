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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function save() {

    // Discover all evaluations in a course.
    $course = $this->getOriginEntity();
    $lectures = $course->getLectures();
    $evaluations = [];
    foreach ($lectures as $lecture) {
      $paragraphs = $lecture->getParagraphs();
      $evaluations = array_merge($evaluations,
        array_filter($paragraphs, fn($p) => $p->bundle() == 'evaluation'));
    }

    // Save all evaluations to update computed fields.
    foreach ($evaluations as $evaluation) {
      $evaluation->save();
    }

    // Continue with parent save.
    parent::save();
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
