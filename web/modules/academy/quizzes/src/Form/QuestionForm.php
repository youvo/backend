<?php

namespace Drupal\quizzes\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Form controller for the paragraph entity edit forms.
 */
class QuestionForm extends ContentEntityForm {

  use QuestionValidateTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    // Build parent form.
    $form = parent::form($form, $form_state);

    // Attach js to hide 'show row weights' buttons.
    $form['#attached']['library'][] = 'academy/hideweightbutton';

    /** @var \Drupal\quizzes\Entity\Question $question */
    $question = $this->getEntity();

    // Type container for validation trait.
    $form['type'] = [
      '#type' => 'hidden',
      '#default_value' => $question->bundle(),
    ];

    // Add answers multi value form element.
    if ($question->bundle() == 'multiple_choice' || $question->bundle() == 'single_choice') {

      // Load default values for answers.
      $default_answers = [];
      $options = $question->get('options')->getValue();
      $answers = $question->get('answers')->getValue();
      foreach (array_keys($options) as $delta) {
        $default_answers[] = [
          'option' => $options[$delta]['value'],
          'correct' => $answers[$delta]['value'],
        ];
      }

      // Attach answers multi value form element.
      $form['answers'] = [
        '#title' => $this->t('Answers'),
        '#type' => 'multivalue',
        '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
        '#description' => $this->t('Specify the potential answers. Check if they are correct. Only one for single-choice question!'),
        '#add_more_label' => $this->t('Add answer'),
        '#default_value' => $default_answers,
        'option' => [
          '#type' => 'textfield',
          '#title' => $this->t('Option'),
          '#title_display' => 'invisible',
        ],
        'correct' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Correct?'),
        ],
        '#weight' => -2,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save entity.
    parent::save($form, $form_state);

    // Add status and logger messages.
    /** @var \Drupal\quizzes\Entity\Question $question */
    /** @var \Drupal\child_entities\ChildEntityInterface $paragraph */
    $question = $this->getEntity();
    $paragraph = $question->getParentEntity();
    $arguments = ['%label' => $question->label()];
    $this->messenger()->addStatus($this->t('The question %label has been updated.', $arguments));

    $form_state->setRedirect('entity.paragraph.edit_form', [
      'lecture' => $paragraph->getParentEntity()->id(),
      'paragraph' => $paragraph->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->validateQuestion($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {

    // Get entitys actions.
    $actions = parent::actions($form, $form_state);

    // Add an abort button.
    /** @var \Drupal\child_entities\ChildEntityInterface $question */
    /** @var \Drupal\child_entities\ChildEntityInterface $paragraph */
    $question = $this->getEntity();
    $paragraph = $question->getParentEntity();
    $url = Url::fromRoute('entity.paragraph.edit_form', [
      'paragraph' => $paragraph->id(),
      'lecture' => $paragraph->getParentEntity()->id(),
    ]);
    $actions['abort'] = [
      '#type' => 'link',
      '#title' => $this->t('Abort'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['button'],
      ],
      '#weight' => 10,
    ];
    return $actions;
  }

}
