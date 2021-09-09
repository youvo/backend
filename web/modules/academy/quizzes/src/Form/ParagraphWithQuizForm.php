<?php

namespace Drupal\quizzes\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Form\ParagraphForm;

/**
 * Form controller for the paragraph entity with quiz edit forms.
 */
class ParagraphWithQuizForm extends ParagraphForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    if ($this->entity->bundle() === 'quiz') {

      // We load the differen question types and append buttons to add a
      // question to the current quiz.
      $question_types = [];
      try {
        $question_types = \Drupal::entityTypeManager()
          ->getStorage('question_type')
          ->loadMultiple();
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        watchdog_exception('Quiz', $e, 'Could not load quiz types in edit form.');
      }
      foreach ($question_types as $question_type) {
        $form['add_' . $question_type->id()] = [
          '#type' => 'submit',
          '#value' => '+ ' . $question_type->label() . ' Question',
          '#weight' => '100',
        ];

      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save entity.
    $result = parent::save($form, $form_state);
  }

}
