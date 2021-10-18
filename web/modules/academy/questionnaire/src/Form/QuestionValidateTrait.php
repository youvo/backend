<?php

namespace Drupal\questionnaire\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a trait for form validation for questions.
 */
trait QuestionValidateTrait {

  /**
   * Require t.
   */
  abstract public function t($string, array $args = [], array $options = []);

  /**
   * Require formatPlural.
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
      $answers = $form_state->getValue('multianswers');
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
    if ($question_type === 'radios' || $question_type === 'checkboxes') {
      $answers = $form_state->getValue('multianswers');
      $correct_set = 0;
      foreach ($answers as $answer) {
        if ($answer['correct']) {
          $correct_set++;
        }
      }
      if ($question_type === 'radios') {
        if ($correct_set != 1) {
          $message = $this->t('Please select one correct answer.');
          $form_state->setErrorByName('elements', $message);
        }
      }
      else {
        if (!$correct_set) {
          $message = $this->t('Please select at least one correct answer.');
          $form_state->setErrorByName('elements', $message);
        }
      }
    }
  }

}
