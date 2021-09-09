<?php

namespace Drupal\quizzes\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Form\ParagraphForm;
use Drupal\quizzes\Entity\Question;

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
          '#data' => $question_type->id(),
          '#weight' => '99',
          '#submit' => ['::rebuildForm'],
          '#ajax' => [
            'callback' => [$this, 'addQuestionToForm'],
            'disable-refocus' => TRUE,
            'event' => 'click',
            'effect' => 'none',
            'progress' => [
              'type' => 'none',
            ],
          ],
        ];
      }

      // We add a container here to append the question forms returned by the
      // AJAX callback.
      $form['question_container'] = [
        '#type' => 'container',
        '#weight' => '100',
      ];
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

  /**
   * Adds a question form to the quiz form.
   */
  public function addQuestionToForm(array &$form, FormStateInterface $form_state) {
    $question_type = $form_state->getTriggeringElement()['#data'];

    $question = Question::create(['bundle' => $question_type]);
    $question_form = \Drupal::service('entity.form_builder')->getForm($question, 'edit');
    unset($question_form['actions']);
    $response = new AjaxResponse();
    $response->addCommand(new AppendCommand('#edit-question-container', $question_form));

    return $response;
  }

  /**
   * We need to rebuild to update form_state.
   */
  public function rebuildForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

}
