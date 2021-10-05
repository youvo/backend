<?php

namespace Drupal\quizzes\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Utility\Error;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\paragraphs\Form\ParagraphForm;
use Drupal\quizzes\Entity\Question;
use Drupal\quizzes\Entity\Quiz;

/**
 * Form controller for the paragraph entity with quiz edit forms.
 */
class ParagraphWithQuizForm extends ParagraphForm {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    if ($this->entity instanceof Quiz) {
      $this->attachQuizForm($form, $form_state);
    }

    return $form;
  }

  /**
   * Adds form elements for quizzes.
   */
  protected function attachQuizForm(&$form, FormStateInterface $form_state) {

    // Delete all queued messages.
    $this->messenger()->deleteAll();

    // Attach js to hide 'show row weights' buttons.
    $form['#attached']['library'][] = 'academy/hideweightbutton';

    // Hide unused form elements.
    $form['uid']['#access'] = FALSE;
    $form['created']['#access'] = FALSE;
    $form['changed']['#access'] = FALSE;
    $form['questions']['#access'] = FALSE;

    // @todo Target wrapper with drupal-data-selector after
    // https://www.drupal.org/project/drupal/issues/2821793
    // is resolved.
    $form['questions'] = [
      '#type' => 'container',
      '#prefix' => '<div id="questions-wrapper">',
      '#suffix' => '</div>',
    ];

    // We add a draggable table to append the questions in the form_state.
    $form['questions']['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#empty' => $this->t('There are no Questions yet.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
    ];

    // Add a queue for questions to delete to garantuee data persistency.
    $question_delete_queue = $form_state->getValue('question_delete_queue') ?? [];
    $form['questions']['question_delete_queue'] = [
      '#type' => 'value',
      '#default_value' => $question_delete_queue,
    ];

    // We compile the ids here to exclude the questions from the table from
    // the next form build.
    $question_delete_queue_ids = [0];
    if (!empty($question_delete_queue)) {
      $question_delete_queue_ids = array_map(function ($q) {
        return $q->id();
      }, $question_delete_queue);
    }

    // Get all questions that have this quiz paragraph as a parent.
    // Or get current entities from form_state and append to form element.
    if ($form_state->getValue('question_entities') === NULL) {
      $questions = [];
      try {
        $questions_storage = $this->entityTypeManager
          ->getStorage('question');
        $questions_query = $questions_storage->getQuery()
          ->condition('paragraph', $this->entity->id())
          ->condition('id', $question_delete_queue_ids, 'NOT IN')
          ->sort('weight')
          ->execute();
        $questions = $questions_storage->loadMultiple($questions_query);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $variables = Error::decodeException($e);
        \Drupal::logger('quiz')
          ->error('An error occurred while loading questions. %type: @message in %function (line %line of %file).', $variables);
      }
    }
    else {
      $questions = $form_state->getValue('question_entities');
    }

    $form['questions']['question_entities'] = [
      '#type' => 'value',
      '#default_value' => $questions,
    ];

    // Get the largest ID or temporary ID from already created questions.
    // Increase by one. This new temporary ID is unique.
    $temp_id = !empty($questions) ?
      max(array_map(function ($q) {
        return $q->id();
      }, $questions)) + 1 : 1;

    // Determine delta for the weight distribution.
    $delta = count($questions);

    // Fill the table with row entries.
    foreach ($questions as $question) {
      $row = $this->buildRow($question, $form_state->hasValue('current_id'));
      if (isset($row['weight'])) {
        $row['weight']['#delta'] = $delta;
      }
      $form['questions']['table'][$question->id()] = $row;
    }

    // Provide containers to transport values between Ajax responses.
    $form['questions']['type'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('type'),
    ];
    $form['questions']['current_id'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('current_id'),
    ];
    // Use an increment to overwrite default value of multivalue field with
    // subsequent Ajax rebuilds.
    $form['questions']['key'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('key') ?? 0,
    ];

    // We load the different question types and append buttons to add a
    // question to the current quiz.
    $form['questions']['add_question'] = [
      '#type' => 'fieldset',
      '#suffix' => '<div class="form-item__description">' . $this->t('Please do not forget to save newly created and changed content by submitting this form!') . '</div>',
      '#weight' => '99',
      '#access' => !$form_state->hasValue('current_id'),
    ];

    $question_types = [];
    try {
      $question_types = $this->entityTypeManager
        ->getStorage('question_type')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('lecture')
        ->error('An error occurred while loading question types. %type: @message in %function (line %line of %file).', $variables);
    }

    foreach ($question_types as $question_type) {
      $form['questions']['add_question'][$question_type->id()] = [
        '#type' => 'button',
        '#value' => '+ ' . $question_type->label() . ' Question',
        '#attributes' => ['data-type' => $question_type->id()],
        '#ajax' => [
          'callback' => [$this, 'showQuestionFieldset'],
          'disable-refocus' => TRUE,
          'event' => 'click',
          'effect' => 'none',
          'progress' => [
            'type' => 'none',
          ],
        ],
      ];
    }

    // Get the current question. Empty if requesting new question.
    if ($form_state->hasValue('current_id') && $form_state->getValue('current_id')) {
      /** @var \Drupal\quizzes\Entity\Question $current_question */
      $current_question = $questions[$form_state->getValue('current_id')];
    }
    else {
      $current_question = NULL;
    }

    // Disallow answers field for free_text question type.
    $answers_access = TRUE;
    if ($form_state->hasValue('type') && $form_state->getValue('type') == 'free_text') {
      $answers_access = FALSE;
    }

    // We add form elements to fill the question entity in the submit-handler.
    // These correspond to the fields in a question entity.
    $form['questions']['elements'] = [
      '#prefix' => '<div id="error-wrapper"></div>',
      '#type' => 'fieldset',
      '#access' => $form_state->hasValue('current_id'),
    ];

    // This is a 'free' temporary id that is used to identify newly created
    // question entities.
    $form['questions']['elements']['temp_id'] = [
      '#type' => 'hidden',
      '#value' => $temp_id,
    ];

    // Manually define the form elements for questions. We have to ensure that
    // these questions represent the fields of a question.
    // @todo Find a way to resolve widget to form array from fieldManager.
    $form['questions']['elements']['body'] = [
      '#title' => $this->t('Question'),
      '#type' => 'textarea',
      '#value' => isset($current_question) ? $current_question->get('body')->value : '',
      '#rows' => 2,
    ];

    $form['questions']['elements']['help'] = [
      '#title' => $this->t('Help Text'),
      '#type' => 'textarea',
      '#description' => $this->t('Further explanation to the question.'),
      '#value' => isset($current_question) ? $current_question->get('help')->value : '',
      '#rows' => 3,
    ];

    $default_answers = [];
    if (isset($current_question) && $answers_access) {
      $options = $current_question->get('options')->getValue();
      $answers = $current_question->get('answers')->getValue();
      foreach (array_keys($options) as $delta) {
        $default_answers[] = [
          'option' => $options[$delta]['value'],
          'correct' => $answers[$delta]['value'],
        ];
      }
    }

    // We use key to replenish default value after rebuild.
    $key = $form_state->hasValue('key') ? $form_state->getValue('key') : 0;
    $form['questions']['elements']['answers']['#tree'] = TRUE;
    $form['questions']['elements']['answers'][$key]['options'] = [
      '#title' => $this->t('Answers'),
      '#type' => 'multivalue',
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#description' => $this->t('Specify the potential answers. Check if they are correct. Only one for single-choice question!'),
      '#add_more_label' => $this->t('Add answer'),
      '#access' => $answers_access ?? TRUE,
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
    ];

    $form['questions']['elements']['explanation'] = [
      '#title' => $this->t('Explanation'),
      '#type' => 'textarea',
      '#description' => $this->t('Explaining the reasoning behind the correct answers.'),
      '#value' => isset($current_question) ? $current_question->get('explanation')->value : '',
      '#rows' => 3,
    ];

    // Trigger a 'submit' for the form elements in the container and create
    // a question entity. Then represent such question entity in the table
    // above.
    $form['questions']['elements']['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Question'),
      '#validate' => ['::validateQuestion'],
      '#submit' => ['::createQuestion'],
      '#attributes' => ['class' => ['button--primary']],
      '#access' => !$form_state->getValue('current_id'),
      '#ajax' => [
        'callback' => '::rebuildAjax',
        'wrapper' => 'questions-wrapper',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
      '#limit_validation_errors' => [
        ['type'],
        ['key'],
        ['question_entities'],
        ['question_delete_queue'],
        ['body'],
        ['help'],
        ['options'],
        ['answers'],
        ['explanation'],
        ['temp_id'],
        ['elements'],
      ],
    ];

    // Use a separate modify submit button to edit given question.
    $form['questions']['elements']['modify'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Question'),
      '#submit' => ['::submitEditQuestion'],
      '#attributes' => ['class' => ['button--primary']],
      '#access' => $form_state->getValue('current_id'),
      '#ajax' => [
        'callback' => '::rebuildAjax',
        'wrapper' => 'questions-wrapper',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
      '#limit_validation_errors' => [
        ['type'],
        ['key'],
        ['question_entities'],
        ['question_delete_queue'],
        ['body'],
        ['help'],
        ['options'],
        ['answers'],
        ['explanation'],
        ['temp_id'],
        ['current_id'],
      ],
    ];

    // We can also abort current creation and rebuild the form as it was
    // before.
    $form['questions']['elements']['abort'] = [
      '#type' => 'submit',
      '#value' => $this->t('Abort'),
      '#submit' => ['::abortQuestion'],
      '#ajax' => [
        'callback' => '::rebuildAjax',
        'wrapper' => 'questions-wrapper',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
      '#limit_validation_errors' => [
        ['key'],
        ['question_entities'],
        ['question_delete_queue'],
      ],
    ];

    // This is a little button to confirm the deletion.
    $form['questions']['confirm_delete'] = [
      '#type' => 'submit',
      '#submit' => ['::deleteQuestion'],
      '#attributes' => ['class' => ['button--extrasmall button--danger align-right visually-hidden']],
      '#value' => $this->t('Confirm Deletion'),
      '#ajax' => [
        'callback' => '::rebuildAjax',
        'disable-refocus' => TRUE,
        'wrapper' => 'questions-wrapper',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
      '#limit_validation_errors' => [
        ['key'],
        ['question_entities'],
        ['current_id'],
        ['question_delete_queue'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save Quiz as Paragraph.
    parent::save($form, $form_state);

    if ($this->entity instanceof Quiz) {
      // Save the new and persistent questions. Also, the references are
      // attached to the current quiz paragraph.
      $quiz_id = $this->entity->id();
      $questions = $form_state->getValue('question_entities');
      $table = $form_state->getValue('table');
      $question_ids = [];
      foreach ($questions as $question) {
        /** @var \Drupal\quizzes\Entity\Question $question */
        $weight = $table[$question->id()]['weight'] ?? 0;
        $question->set('weight', $weight);
        $question->set('paragraph', $quiz_id);
        // Unset the ID to automatically determine valid ID instead of using the
        // temporary ID.
        if ($question->isNew()) {
          unset($question->id);
        }
        $question->save();
        $question_ids[] = ['target_id' => $question->id()];
      }
      $this->entity->set('questions', $question_ids);
      $this->entity->save();

      // Delete stale questions from the delete queue.
      $question_delete_queue = $form_state->getValue('question_delete_queue');
      foreach ($question_delete_queue as $question) {
        $question->delete();
      }
    }
  }

  /**
   * Adds a question form to the quiz form.
   */
  public function showQuestionFieldset(array &$form, FormStateInterface $form_state) {
    // We inject the question type into the form_state in order to use it later
    // in the submit-handler.
    $question_type = $form_state->getTriggeringElement()['#attributes']['data-type'];

    $response = new AjaxResponse();

    // Hide deletion confirm button.
    $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-confirm-delete]', 'addClass', ['visually-hidden']));

    // Hide modify button and show new button.
    $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-create]', 'removeClass', ['visually-hidden']));
    $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-modify]', 'addClass', ['visually-hidden']));

    // Deactivate buttons temporarily in table.
    $response->addCommand(new invokeCommand('input[data-drupal-selector$=buttons-edit]', 'attr', ['disabled', 'true']));
    $response->addCommand(new invokeCommand('input[data-drupal-selector$=buttons-delete]', 'attr', ['disabled', 'true']));

    // Empty all field values.
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-body]', 'val', ['']));
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-help]', 'val', ['']));
    $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-answers]', 'val', ['']));
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-explanation]', 'val', ['']));

    // Disable answer options and correct answers for free text question.
    if ($question_type === 'free_text') {
      $response->addCommand(new invokeCommand('div[data-drupal-selector=edit-answers]', 'addClass', ['visually-hidden']));
    }

    // Make form element body required.
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-body]', 'attr', ['required', 'true']));
    $response->addCommand(new invokeCommand('label[for^=edit-body]', 'addClass', ['form-required']));

    // Make form elements required for multiple-/single choice questions.
    if ($question_type === 'single_choice' || $question_type === 'multiple_choice') {
      $response->addCommand(new invokeCommand('h4.form-item__label--multiple-value-form', 'addClass', ['form-required']));
    }

    // Set hidden value for type.
    $response->addCommand(new invokeCommand('input[name=type]', 'val', [$question_type]));

    // Show and hide corresponding fieldsets.
    $response->addCommand(new invokeCommand('fieldset[data-drupal-selector=edit-add-question]', 'addClass', ['hidden']));
    $response->addCommand(new invokeCommand('fieldset[data-drupal-selector=edit-elements]', 'removeClass', ['hidden']));

    return $response;
  }

  /**
   * Get the entity values from form_state to populate form fields.
   */
  public function editQuestion(array $form, FormStateInterface &$form_state) {
    $question_id = $form_state->getTriggeringElement()['#attributes']['data-id'];
    $question_type = $form_state->getTriggeringElement()['#attributes']['data-type'];
    $form_state->setValue('key', $form_state->getValue('key') + 1);
    $form_state->setValue('current_id', $question_id);
    $form_state->setValue('type', $question_type);
    $form_state->setRebuild();
  }

  /**
   * Abort question.
   */
  public function abortQuestion(array $form, FormStateInterface &$form_state) {
    $form_state->setValue('key', $form_state->getValue('key') + 1);
    $form_state->setRebuild();
  }

  /**
   * Rebuilds the form or delivers form errors from validation.
   */
  public function rebuildAjax(array $form, FormStateInterface &$form_state) {
    if (!$form_state->hasAnyErrors()) {
      return $form['questions'];
    }
    else {
      $response = new AjaxResponse();
      $errors = $form_state->getErrors();
      foreach ($errors as $error) {
        $response->addCommand(new MessageCommand($error->render(),
            '#error-wrapper',
            ['type' => 'error']));
      }
      return $response;
    }
  }

  /**
   * Rebuilds the form or delivers form errors from validation.
   */
  public function completeRebuildAjax(array $form, FormStateInterface &$form_state) {
    return $form['questions'];
  }

  /**
   * Validates form fields for creating a question.
   */
  public function validateQuestion(array &$form, FormStateInterface $form_state) {
    $question_type = $form_state->getValue('type');

    // Check if all required fields are filled.
    $required_fields = [];
    if (empty($form_state->getValue('body'))) {
      $required_fields[] = $this->t('Question');
    }
    // If ($question_type === 'single_choice' || $question_type === 'multiple_choice') {
    //      if (empty($form_state->getValue('options'))) {
    //        $required_fields[] = $this->t('Options');
    //      }
    //      if (empty($form_state->getValue('answers'))) {
    //        $required_fields[] = $this->t('Answer(s)');
    //      }
    //    }
    if (!empty($required_fields)) {
      $message = $this->formatPlural(
        count($required_fields),
        'The field %field is required.',
        'The fields %fields are required.', [
          '%field' => reset($required_fields),
          '%fields' => implode(', ', $required_fields),
        ]);
      $form_state->setErrorByName('elements', $message);
    }

    // Check if options and answers fields have the correct format.
    $invalid = FALSE;
    // $count_options = count(explode('&', $form_state->getValue('options')));
    //    $answers = explode('&', $form_state->getValue('answers'));
    //    $count_answers = count($answers);
    //    if ($question_type === 'multiple_choice') {
    //      if ($count_answers > $count_options) {
    //        $invalid = TRUE;
    //      }
    //      foreach ($answers as $answer) {
    //        if (!is_numeric($answer) || $answer < 1 || $answer > $count_options) {
    //          $invalid = TRUE;
    //        }
    //      }
    //    }
    //    if ($question_type === 'single_choice') {
    //      if (!($count_answers == 1)) {
    //        $invalid = TRUE;
    //      }
    //      $answer = reset($answers);
    //      if (!is_numeric($answer) || $answer < 1 || $answer > $count_options) {
    //        $invalid = TRUE;
    //      }
    //    }
    if ($invalid) {
      $message = $this->t('Options and answers have an invalid format.');
      $form_state->setErrorByName('elements', $message);
    }
  }

  /**
   * Creates new question and adds it to form_state.
   */
  public function createQuestion(array &$form, FormStateInterface $form_state) {
    // We get the form values and append a newly created question of the
    // requested type to the form_state.
    $questions = $form_state->getValue('question_entities');
    $new_question = Question::create([
      'bundle' => $form_state->getValue('type'),
      'body' => $form_state->getValue('body'),
      'help' => $form_state->getValue('help'),
      'explanation' => $form_state->getValue('explanation'),
      'id' => $form_state->getValue('temp_id'),
    ]);
    $answers = $form_state->getValue('answers');
    foreach ($answers as $answer) {
      $new_question->get('options')->appendItem($answer['option']);
      $new_question->get('answers')->appendItem($answer['correct']);
    }
    $new_question->enforceIsNew();
    $questions[$new_question->id()] = $new_question;
    $form_state->setValue('question_entities', $questions);
    $form_state->setRebuild();
  }

  /**
   * Edits questions in form_state.
   */
  public function submitEditQuestion(array &$form, FormStateInterface $form_state) {
    // Get the edited question.
    $question_id = $form_state->getValue('current_id');
    $questions = $form_state->getValue('question_entities');
    $question = $this->getRequestedQuestion($questions, $question_id);

    // Just create a new question, if the prior question was new.
    if ($question->isNew()) {
      $form_state->setValue('temp_id', $question_id);
      $this->createQuestion($form, $form_state);
    }
    else {
      $question->set('bundle', $form_state->getValue('type'));
      $question->set('body', $form_state->getValue('body'));
      $question->set('help', $form_state->getValue('help'));
      $question->set('answers', $form_state->getValue('answers'));
      $question->set('explanation', $form_state->getValue('explanation'));
      $questions[$question->id()] = $question;
      $form_state->setValue('question_entities', $questions);
      $form_state->setRebuild();
    }
  }

  /**
   * Prepopulate hidden fields for deletion.
   */
  public function prepareDelete(array &$form, FormStateInterface $form_state) {

    $question_id = $form_state->getTriggeringElement()['#attributes']['data-id'];

    $response = new AjaxResponse();

    // Show delete confirm button and append to current delete button.
    $response->addCommand(new invokeCommand('input[name=current_id]', 'val', [$question_id]));

    // Set hidden value for current_id.
    $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-confirm-delete', 'prependTo', ['div#buttons-' . $question_id]));
    $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-confirm-delete]', 'removeClass', ['visually-hidden']));

    return $response;
  }

  /**
   * Remove questions from the table and queues delete for persitent questions.
   */
  public function deleteQuestion(array &$form, FormStateInterface $form_state) {
    // Get the edited question.
    $question_id = $form_state->getValue('current_id');
    $questions = $form_state->getValue('question_entities');
    $question = $this->getRequestedQuestion($questions, $question_id);

    // If the question is not new, we need to delete the entity. The question is
    // put into the delete queue and deleted during form save.
    if (!$question->isNew()) {
      $question_delete_queue = $form_state->getValue('question_delete_queue');
      $question_delete_queue[] = $question;
      $form_state->setValue('question_delete_queue', $question_delete_queue);
    }

    unset($questions[$question_id]);
    $form_state->setValue('question_entities', $questions);
    $form_state->setRebuild();
  }

  /**
   * Gives header for table of questions.
   */
  protected function buildHeader() {
    $header['type'] = $this->t('Type');
    $header['body'] = $this->t('Question');
    $header['buttons'] = '';
    $header['weight'] = [
      'data' => $this->t('Weight'),
      'class' => ['tabledrag-hide'],
    ];
    return $header;
  }

  /**
   * Gives rows for table of questions.
   */
  protected function buildRow($question, $buttons_disabled) {
    // Get bundle for question entity.
    /** @var \Drupal\quizzes\QuestionInterface $question */
    $bundle = $this->entityTypeManager
      ->getStorage('question_type')
      ->load($question->bundle())
      ->label();
    if (!$buttons_disabled) {
      $row['#attributes']['class'][] = 'draggable';
    }
    $row['#weight'] = $question->get('weight')->value;
    $row['type'] = [
      '#markup' => $bundle,
    ];
    $row['body'] = [
      '#markup' => $question->get('body')->value,
    ];
    $row['buttons']['delete'] = [
      '#type' => 'button',
      '#prefix' => '<div id="buttons-' . $question->id() . '">',
      '#attributes' => [
        'class' => ['button--extrasmall align-right'],
        'data-id' => $question->id(),
        'data-type' => $question->bundle(),
      ],
      '#value' => $this->t('Delete'),
      '#name' => 'delete-' . $question->id(),
      '#disabled' => $buttons_disabled,
      '#ajax' => [
        'callback' => '::prepareDelete',
        'disable-refocus' => TRUE,
        'event' => 'click',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];
    $row['buttons']['edit'] = [
      '#type' => 'submit',
      '#suffix' => '</div>',
      '#submit' => ['::editQuestion'],
      '#attributes' => [
        'class' => ['button--extrasmall align-right'],
        'data-id' => $question->id(),
        'data-type' => $question->bundle(),
      ],
      '#value' => $this->t('Edit'),
      '#name' => 'edit-' . $question->id(),
      '#disabled' => $buttons_disabled,
      '#ajax' => [
        'callback' => '::rebuildAjax',
        'wrapper' => 'questions-wrapper',
        'event' => 'click',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
      '#limit_validation_errors' => [
        ['question_entities'],
        ['current_id'],
        ['question_delete_queue'],
        ['type'],
        ['key'],
      ],
    ];
    $row['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight for Question.'),
      '#title_display' => 'invisible',
      '#default_value' => $question->get('weight')->value,
      '#attributes' => ['class' => ['weight']],
    ];
    return $row;
  }

  /**
   * Searches for the question in an array of question objects.
   *
   * @param array $questions
   *   Array of Question objects.
   * @param int $question_id
   *   Requested question id.
   *
   * @return \Drupal\quizzes\Entity\Question
   *   The requested question.
   */
  protected function getRequestedQuestion(array $questions, int $question_id) {
    $question = array_filter($questions, function ($q) use ($question_id) {
      return $q->id() == $question_id;
    });
    return reset($question);
  }

}
