<?php

namespace Drupal\questionnaire\Plugin\Field;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Utility\Error;
use Drupal\questionnaire\Entity\Questionnaire;

/**
 * Computes questions contained in course.
 */
class ContainedQuestionsFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Computes the field value.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function computeValue(): void {

    $all_question_ids = [];

    // Get current entity.
    /** @var \Drupal\child_entities\ChildEntityInterface $paragraph */
    $paragraph = $this->getEntity();

    try {
      // Get all questions contained in a course.
      /** @var \Drupal\courses\Entity\Course $course */
      $course = $paragraph->getOriginEntity();

      // First get all questionnaires in lectures.
      $lectures = $course->getLectures();
      $questionnaires = [];
      foreach ($lectures as $lecture) {
        // The paragraphs are weighted correctly.
        $paragraphs = $lecture->getParagraphs();
        $questionnaires[] = array_filter($paragraphs, static fn($p) => $p->bundle() === 'questionnaire');
      }
      $questionnaires = array_merge(...$questionnaires);

      // Compile all questions within all questionnaires.
      foreach ($questionnaires as $questionnaire) {
        $questions = $questionnaire instanceof Questionnaire ?
          $questionnaire->getQuestions() : [];
        // Questions are not weighted correctly. Therefore, sort them.
        usort($questions, static fn($a, $b) => $a->get('weight')->value <=> $b->get('weight')->value);
        $all_question_ids[] = array_map(static fn ($q) => $q->id(), $questions);
      }
      $all_question_ids = array_merge(...$all_question_ids);
    }
    catch (PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('academy')
        ->error('Unable to resolve questions contained in course. %type: @message in %function (line %line of %file).', $variables);
    }

    // Attach all questions as references.
    $this->setValue(array_map(static fn ($id) => ['target_id' => $id], $all_question_ids));
  }

}
