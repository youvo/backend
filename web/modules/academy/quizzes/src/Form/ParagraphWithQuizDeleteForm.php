<?php

namespace Drupal\quizzes\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the paragraph entity edit forms.
 */
class ParagraphWithQuizDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save entity.
    $result = parent::save($form, $form_state);
  }

}
