<?php

namespace Drupal\questionnaire\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Form controller for the paragraph entity edit forms.
 *
 * @todo Issue #11: Note administrators about revisions of questions (soft edit).
 */
class QuestionForm extends ContentEntityForm {

  use QuestionProcessTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    // Build parent form.
    $form = parent::form($form, $form_state);

    // Attach js to hide 'show row weights' buttons.
    $form['#attached']['library'][] = 'academy/hideweightbutton';

    /** @var \Drupal\questionnaire\Entity\Question $question */
    $question = $this->getEntity();

    // Type container for validation trait.
    $form['type'] = [
      '#type' => 'hidden',
      '#default_value' => $question->bundle(),
    ];

    // Add answers multi value form element.
    if ($question->bundle() == 'checkboxes' || $question->bundle() == 'radios') {

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
      $form['multianswers'] = [
        '#title' => $this->t('Answers'),
        '#type' => 'multivalue',
        '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
        '#description' => $this->t('Specify the potential answers. Check if they are correct. Only one for radios question!'),
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
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {

    // Describe relevant entities.
    /** @var \Drupal\questionnaire\Entity\Question $question */
    /** @var \Drupal\child_entities\ChildEntityInterface $paragraph */
    $question = $this->getEntity();
    $paragraph = $question->getParentEntity();

    // Add values from multianswers form element.
    if ($form_state->getValue('type') == 'checkboxes' ||
      $form_state->getValue('type') == 'radios') {
      $this->populateMultiAnswerToQuestion($question, $form_state);
    }

    // Save entity.
    parent::save($form, $form_state);

    // Save questionnaire.
    $paragraph->save();

    // Add status and logger messages.
    $arguments = ['%label' => $question->label()];
    $this->messenger()->addStatus($this->t('The question %label has been updated.', $arguments));

    /** @var \Drupal\child_entities\ChildEntityInterface $lecture */
    $lecture = $paragraph->getParentEntity();
    $form_state->setRedirect('entity.paragraph.edit_form', [
      'lecture' => $lecture->id(),
      'course' => $lecture->getParentEntity()->id(),
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
    /** @var \Drupal\child_entities\ChildEntityInterface $lecture */
    $question = $this->getEntity();
    $paragraph = $question->getParentEntity();
    $lecture = $paragraph->getParentEntity();
    $url = Url::fromRoute('entity.paragraph.edit_form', [
      'lecture' => $lecture->id(),
      'course' => $lecture->getParentEntity()->id(),
      'paragraph' => $paragraph->id(),
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
