<?php

namespace Drupal\questionnaire\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\questionnaire\Entity\Question;

/**
 * Provides a trait for form validation for questions.
 */
trait QuestionProcessTrait {

  /**
   * Require t().
   */
  abstract public function t($string, array $args = [], array $options = []);

  /**
   * Require formatPlural().
   */
  abstract public function formatPlural($count, $singular, $plural, array $args = [], array $options = []);

  /**
   * Validates form fields for creating a question.
   */
  public function validateQuestion(array &$form, FormStateInterface $form_state) {

    // Get current question type.
    $question_type = $form_state->getValue('type');

    // Check if all required fields are filled.
    $required_fields = [];
    if (empty($form_state->getValue('body'))) {
      $required_fields[] = $this->t('Question');
    }
    if ($question_type === 'radios' || $question_type === 'checkboxes') {
      $answers = $form_state->getValue('multi_answers');
      $option_set = 0;
      foreach ($answers as $answer) {
        if (!empty($answer['option'])) {
          $option_set++;
        }
      }
      if (!$option_set) {
        $required_fields[] = $this->t('Answers');
      }
    }
    if (!empty($required_fields)) {
      $message = $this->formatPlural(
        count($required_fields),
        'The field %field is required.',
        'The fields %fields are required.', [
          '%field' => reset($required_fields),
          '%fields' => implode(' and ', $required_fields),
        ]);
      $form_state->setErrorByName('elements', $message);
    }

    // Check if correct options are satisfying.
    if ($question_type === 'radios') {
      $answers = $form_state->getValue('multi_answers');
      $correct_set = 0;
      foreach ($answers as $answer) {
        if ($answer['correct']) {
          $correct_set++;
        }
      }
      if ($correct_set > 1) {
        $message = $this->t('Please select at most one correct answer.');
        $form_state->setErrorByName('elements', $message);
      }
    }
  }

  /**
   * Adds answers to question from form_state.
   */
  public function populateMultiAnswerToQuestion(Question &$question, FormStateInterface $form_state) {
    $answers = $form_state->getValue('multi_answers');
    $correct_set = count(array_filter($answers, fn($a) => $a['correct']));
    $question->set('options', []);
    $question->set('answers', []);
    foreach ($answers as $answer) {
      if (!empty($answer['option'])) {
        $question->get('options')->appendItem($answer['option']);
        if ($correct_set) {
          $question->get('answers')->appendItem($answer['correct']);
        }
      }
    }
  }

}
