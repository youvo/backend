<?php

namespace Drupal\quizzes\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Form\ParagraphForm;
use Drupal\quizzes\Entity\Question;

/**
 * Form controller for the paragraph entity with quiz edit forms.
 */
class ParagraphWithQuizForm extends ParagraphForm {

  /**
   * Array of questions added to the form.
   *
   * @var \Drupal\quizzes\Entity\Question[]
   */
  protected array $questions = [];

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    if ($this->entity->bundle() === 'quiz') {

      // Hide unused form elements.
      // @todo Remove revision when Paragraph was updated.
      $form['revision']['#access'] = FALSE;
      $form['revision_log']['#access'] = FALSE;
      $form['revision_information']['#access'] = FALSE;
      $form['uid']['#access'] = FALSE;
      $form['created']['#access'] = FALSE;
      $form['changed']['#access'] = FALSE;

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

      // Get current entities from form_state and append to form element.
      $questions = $form_state->getValue('entities') ?? [];
      $form['questions']['entities'] = [
        '#type' => 'value',
        '#default_value' => $questions,
      ];

      // Newly created entities do not have an ID yet. Just use an iterator that
      // is larger than the IDs of the persistent entities.
      $largest_id = 1;
      $id = $largest_id + 1;

      // Determine delta for the weight distribution.
      $delta = count($questions);

      // Fill the table with row entries.
      foreach ($questions as $question) {
        $row = $this->buildRow($question);
        if (isset($row['weight'])) {
          $row['weight']['#delta'] = $delta;
        }
        $form['questions']['table'][$id] = $row;
        $id++;
      }

      // We load the different question types and append buttons to add a
      // question to the current quiz.
      $form['questions']['add_question'] = [
        '#type' => 'fieldset',
        '#weight' => '99',
      ];

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
        $form['questions']['add_question'][$question_type->id()] = [
          '#type' => 'button',
          '#value' => '+ ' . $question_type->label() . ' Question',
          '#data' => $question_type->id(),
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

      // We add form elements to fill the question entity in the submit-handler.
      // These correspond to the fields in a question entity.
      // Note that we treat Question as a programmatic entity and there is no
      // other was to edit questions beside this form. Therefore, we must
      // manually garantuee that all fields are represented here.
      $form['questions']['elements'] = [
        '#type' => 'fieldset',
        '#attributes' => ['class' => ['hidden']],
      ];

      $form['questions']['elements']['type'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];

      $form['questions']['elements']['body'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Body'),
      ];

      // Trigger a 'submit' for the form elements in the container and create
      // a question entity. Then represent such question entity in the table
      // above.
      $form['questions']['elements']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Question'),
        '#submit' => ['::createQuestion'],
        '#attributes' => ['class' => ['button--primary']],
        '#ajax' => [
          'callback' => '::rebuildAjax',
          'wrapper' => 'questions-wrapper',
          'effect' => 'none',
          'progress' => [
            'type' => 'none',
          ],
        ],
        '#limit_validation_errors' => [['type'], ['entities']],
      ];

      // We can also abort current creation and rebuild the form as it was
      // before.
      $form['questions']['elements']['abort'] = [
        '#type' => 'button',
        '#value' => $this->t('Abort'),
        '#ajax' => [
          'callback' => '::rebuildAjax',
          'wrapper' => 'questions-wrapper',
          'effect' => 'none',
          'progress' => [
            'type' => 'none',
          ],
        ],
        '#limit_validation_errors' => [['type'], ['entities']],
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
  public function showQuestionFieldset(array &$form, FormStateInterface $form_state) {
    $question_type = $form_state->getTriggeringElement()['#data'];

    // $question = Question::create(['bundle' => $question_type]);
    $response = new AjaxResponse();
    $this->disableAllEditButtons($response);
    $response->addCommand(new invokeCommand('input[data-drupal-selector$="buttons-edit"]', 'addClass', ['hidden']));
    $response->addCommand(new invokeCommand('fieldset[data-drupal-selector=edit-add-question]', 'addClass', ['hidden']));
    $response->addCommand(new invokeCommand('input[name=type]', 'val', [$question_type]));
    $response->addCommand(new invokeCommand('fieldset[data-drupal-selector=edit-elements]', 'removeClass', ['hidden']));
    return $response;
  }

  /**
   * Aborts current question and resets quiz form.
   */
  public function rebuildAjax(array $form, FormStateInterface &$form_state) {
    return $form['questions'];
  }

  /**
   * Aborts current question and resets quiz form.
   */
  public function createQuestion(array &$form, FormStateInterface $form_state) {

    $question_type = $form_state->getValue('type');
    $questions = $form_state->getValue('entities');
    $questions[] = Question::create(['bundle' => $question_type]);
    $form_state->setValue('entities', $questions);
    $form_state->setRebuild();
  }

  /**
   * Aborts current question and resets quiz form.
   */
  public function resetFieldsets(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $this->enableAllEditButtons($response);
    $this->resetQuestionForm($response);
    return $response;
  }

  /**
   * We need to rebuild to update form_state.
   */
  public function rebuildForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * Gives header for table of questions.
   */
  protected function disableAllEditButtons(&$response) {

  }

  /**
   * Gives header for table of questions.
   */
  protected function enableAllEditButtons(&$response) {

  }

  /**
   * Gives header for table of questions.
   */
  protected function resetQuestionForm(&$response) {
    $this->enableAllEditButtons($response);
    $response->addCommand(new invokeCommand('fieldset[data-drupal-selector=edit-elements]', 'addClass', ['hidden']));
    $response->addCommand(new invokeCommand('input[name=type]', 'val', ['']));
    $response->addCommand(new invokeCommand('fieldset[data-drupal-selector=edit-add-question]', 'removeClass', ['hidden']));
  }

  /**
   * Gives header for table of questions.
   */
  protected function buildHeader() {
    $header['type'] = $this->t('Name');
    $header['body'] = $this->t('Preview');
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
  protected function buildRow($question) {
    // Get bundle for question entity.
    /** @var \Drupal\quizzes\QuestionInterface $question */
    $bundle = '';
    try {
      $bundle = \Drupal::entityTypeManager()
        ->getStorage('question_type')
        ->load($question->bundle())
        ->label();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      watchdog_exception('Quiz Form: Could not fetch bundles.', $e);
    }
    $row['#attributes']['class'][] = 'draggable';
    $row['#weight'] = $question->get('weight')->value;
    $row['type'] = [
      '#markup' => $bundle,
    ];
    $row['body'] = [
      '#markup' => 'Test Body Description ...',
    ];
    $row['buttons']['delete'] = [
      '#type' => 'button',
      '#data' => $question->id(),
      '#attributes' => ['class' => ['button--extrasmall align-right']],
      '#value' => $this->t('Delete'),
    ];
    $row['buttons']['edit'] = [
      '#type' => 'button',
      '#data' => $question->id(),
      '#attributes' => ['class' => ['button--extrasmall align-right']],
      '#value' => $this->t('Edit'),
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

}
