<?php

namespace Drupal\questionnaire\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
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
  public function getQuestions(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $questions_field */
    $questions_field = $this->get('questions');
    /** @var \Drupal\questionnaire\Entity\Question[] $questions */
    $questions = $questions_field->referencedEntities();
    return $questions;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {

    parent::postSave($storage, $update);

    // If questionnaire is updated all evaluations in this course need updating.
    // Invalidate cache to recalculate referenced questions in evaluations.
    /** @var \Drupal\courses\Entity\Course $course */
    $course = $this->getOriginEntity();
    $lectures = $course->getLectures();
    $evaluations = [];
    foreach ($lectures as $lecture) {
      $paragraphs = $lecture->getParagraphs();
      $evaluations[] = array_filter($paragraphs, static fn($p) => $p->bundle() === 'evaluation');
    }
    $evaluations = array_filter(array_merge(...$evaluations));

    $tags = [];
    foreach ($evaluations as $evaluation) {
      $tags[] = 'paragraph:' . $evaluation->id();
    }

    Cache::invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
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
