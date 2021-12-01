<?php

namespace Drupal\questionnaire\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Entity\EntityStorageInterface;

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
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // If questionnaire is updated all evaluations in this course need updating.
    // Invalidate cache to recalculate referenced questions in evaluations.
    $course = $this->getOriginEntity();
    $lectures = $course->getLectures();
    $evaluations = [];
    foreach ($lectures as $lecture) {
      $paragraphs = $lecture->getParagraphs();
      $evaluations = array_merge($evaluations,
        array_filter($paragraphs, fn($p) => $p->bundle() == 'evaluation'));
    }
    $tags = [];
    foreach ($evaluations as $evaluation) {
      $tags[] = 'paragraph:' . $evaluation->id();
    }
    Cache::invalidateTags($tags);
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
