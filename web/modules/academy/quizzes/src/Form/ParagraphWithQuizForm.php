<?php

namespace Drupal\quizzes\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Form\ParagraphForm;

/**
 * Form controller for the paragraph entity with quiz edit forms.
 */
class ParagraphWithQuizForm extends ParagraphForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save entity.
    $result = parent::save($form, $form_state);
  }

}
