<?php

namespace Drupal\quizzes\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Form\ParagraphForm;
use Drupal\quizzes\Entity\Question;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the paragraph entity with quiz edit forms.
 */
class ParagraphWithQuizForm extends ParagraphForm {

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   The field manager service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository,
                              EntityTypeBundleInfoInterface $entity_type_bundle_info,
                              TimeInterface $time,
                              EntityFieldManager $field_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_field.manager'),
    );
  }

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
      $form['field_questions']['#access'] = FALSE;

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

      // Get all questions that have this quiz paragraph as a parent.
      // Or get current entities from form_state and append to form element.
      if (empty($form_state->getValue('question_entities'))) {
        $questions_storage = $this->entityTypeManager
          ->getStorage('question');
        $questions_query = $questions_storage->getQuery()
          ->condition('paragraph', $this->entity->id())
          ->sort('weight')
          ->execute();
        $questions = $questions_storage->loadMultiple($questions_query);
      }
      else {
        $questions = $form_state->getValue('question_entities');
      }

      $form['questions']['question_entities'] = [
        '#type' => 'value',
        '#default_value' => $questions,
      ];

      // Newly created entities do not have an ID yet. Just use an iterator that
      // is larger than the IDs of the persistent entities.
      $temp_id = !empty($questions) ?
        max(array_map(function ($q) {
          return $q->id();
        }, $questions)) + 1 : 1;

      // Determine delta for the weight distribution.
      $delta = count($questions);

      // Fill the table with row entries.
      foreach ($questions as $question) {
        $row = $this->buildRow($question, $temp_id);
        if (isset($row['weight'])) {
          $row['weight']['#delta'] = $delta;
        }
        $form['questions']['table'][$temp_id] = $row;
        $temp_id++;
      }

      // We load the different question types and append buttons to add a
      // question to the current quiz.
      $form['questions']['add_question'] = [
        '#type' => 'fieldset',
        '#weight' => '99',
      ];

      $question_types = $this->entityTypeManager
        ->getStorage('question_type')
        ->loadMultiple();

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

      // We add form elements to fill the question entity in the submit-handler.
      // These correspond to the fields in a question entity.
      $form['questions']['elements'] = [
        '#type' => 'fieldset',
        '#attributes' => ['class' => ['hidden']],
      ];

      // This field gets populated by the ajax response.
      $form['questions']['elements']['type'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];

      // This field gets populated by the ajax response.
      $form['questions']['elements']['current_id'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];

      // This is a 'free' temporary id that is used to identify newly created
      // question entities.
      $form['questions']['elements']['temp_id'] = [
        '#type' => 'hidden',
        '#default_value' => $temp_id,
      ];

      // Fetch the base fields from the question entity definition.
      // We exclude non-content fields and then build the render array manually.
      $question_fields = $this->fieldManager
        ->getBaseFieldDefinitions('question');
      $excluded_base_fields = [
        'id',
        'uuid',
        'langcode',
        'bundle',
        'uid',
        'created',
        'changed',
        'weight',
        'paragraph',
        'default_langcode',
      ];

      foreach ($question_fields as $question_field) {
        /** @var \Drupal\Core\Field\BaseFieldDefinition $question_field */
        if (!in_array(strtolower($question_field->getName()), $excluded_base_fields)) {
          $display_options = $question_field->getDisplayOptions('form');
          $form['questions']['elements'][$question_field->getName()] = [
            '#title' => $question_field->getLabel()->render(),
            '#type' => $display_options['type'],
            '#rows' => $display_options['rows'] ?? '',
            '#placeholder' => $display_options['placeholder'] ?? '',
            '#description' => $question_field->getDescription()->render(),
          ];
        }
      }

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
        '#limit_validation_errors' => [
          ['type'],
          ['question_entities'],
          ['body'],
          ['help'],
          ['options'],
          ['answers'],
          ['explanation'],
          ['temp_id'],
        ],
      ];

      // Use a separate modify submit button to edit given question.
      $form['questions']['elements']['modify'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit Question'),
        '#submit' => ['::editQuestion'],
        '#attributes' => ['class' => ['button--primary']],
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
          ['question_entities'],
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
        '#limit_validation_errors' => [
          ['type'],
          ['question_entities'],
        ],
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
    // Save Quiz as Paragraph.
    parent::save($form, $form_state);

    $quiz_id = $this->entity->id();
    $questions = $form_state->getValue('question_entities');
    $question_ids = [];
    foreach ($questions as $question) {
      $question->set('paragraph', $quiz_id);
      $question->save();
      $question_ids[] = ['target_id' => $question->id()];
    }
    $this->entity->set('field_questions', $question_ids);
    $this->entity->save();
  }

  /**
   * Adds a question form to the quiz form.
   */
  public function showQuestionFieldset(array &$form, FormStateInterface $form_state) {
    // We inject the question type into the form_state in order to use it later
    // in the submit-handler.
    $question_type = $form_state->getTriggeringElement()['#attributes']['data-type'];

    $response = new AjaxResponse();

    // Deactivate buttons temporarily in table.
    $response->addCommand(new invokeCommand('input[data-drupal-selector$=buttons-edit]', 'attr', ['disabled', 'true']));
    $response->addCommand(new invokeCommand('input[data-drupal-selector$=buttons-delete]', 'attr', ['disabled', 'true']));

    // Empty all field values.
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-body]', 'val', ['']));
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-help]', 'val', ['']));
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-options]', 'val', ['']));
    $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-answers]', 'val', ['']));
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-explanation]', 'val', ['']));

    // Disable answer options and correct answers for free text question.
    if ($question_type === 'free_text') {
      $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-options]', 'attr', ['disabled', 'true']));
      $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-options]', 'addClass', ['visually-hidden']));
      $response->addCommand(new invokeCommand('label[for^=edit-options]', 'addClass', ['visually-hidden']));
      $response->addCommand(new invokeCommand('div[id^=edit-options]', 'addClass', ['visually-hidden']));
      $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-answers]', 'attr', ['disabled', 'true']));
      $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-answers]', 'addClass', ['visually-hidden']));
      $response->addCommand(new invokeCommand('label[for^=edit-answers]', 'addClass', ['visually-hidden']));
      $response->addCommand(new invokeCommand('div[id^=edit-answers]', 'addClass', ['visually-hidden']));
    }

    // Make form element body required.
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-body]', 'attr', ['required', 'true']));
    $response->addCommand(new invokeCommand('label[for^=edit-body]', 'addClass', ['form-required']));

    // Make form elements required for multiple-/single choice questions.
    if ($question_type === 'single_choice' || $question_type === 'multiple_choice') {
      $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-options]', 'attr', ['required', 'true']));
      $response->addCommand(new invokeCommand('label[for^=edit-options]', 'addClass', ['form-required']));
      $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-answers]', 'attr', ['required', 'true']));
      $response->addCommand(new invokeCommand('label[for^=edit-answers]', 'addClass', ['form-required']));
    }

    // Change placeholder for single choice questions.
    if ($question_type === 'single_choice') {
      $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-answers]', 'attr', ['placeholder', '1']));
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
  public function setQuestionDefaultValues(array $form, FormStateInterface &$form_state) {

    $response = $this->showQuestionFieldset($form, $form_state);

    // Identify the requested question.
    $question_id = $form_state->getTriggeringElement()['#attributes']['data-id'];
    $question_type = $form_state->getTriggeringElement()['#attributes']['data-type'];
    $questions = $form_state->getValue('question_entities');
    $question = $this->getRequestedQuestion($questions, $question_id)['question'];

    // Populate form fields.
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-body]', 'val', [$question->get('body')->value]));
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-help]', 'val', [$question->get('help')->value]));
    $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-explanation]', 'val', [$question->get('explanation')->value]));
    if ($question_type === 'single_choice' || $question_type === 'multiple_choice') {
      $response->addCommand(new invokeCommand('textarea[data-drupal-selector=edit-options]', 'val', [$question->get('options')->value]));
      $response->addCommand(new invokeCommand('input[data-drupal-selector=edit-answers]', 'val', [$question->get('answers')->value]));
    }

    // Set hidden value for current_id.
    $response->addCommand(new invokeCommand('input[name=current_id]', 'val', [$question_id]));

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
    // We get the form values and append a newly created question of the
    // requested type to the form_state.
    $questions = $form_state->getValue('question_entities');
    $new_question = Question::create([
      'bundle' => $form_state->getValue('type'),
      'body' => $form_state->getValue('body'),
      'help' => $form_state->getValue('help'),
      'answers' => $form_state->getValue('answers'),
      'explanation' => $form_state->getValue('explanation'),
      'id' => $form_state->getValue('temp_id'),
    ]);
    $new_question->enforceIsNew();
    $questions[] = $new_question;
    $form_state->setValue('question_entities', $questions);
    $form_state->setRebuild();
  }

  /**
   * Aborts current question and resets quiz form.
   */
  public function editQuestion(array &$form, FormStateInterface $form_state) {
    // Get the edited question.
    $question_id = $form_state->getValue('current_id');
    $questions = $form_state->getValue('question_entities');
    $question_request = $this->getRequestedQuestion($questions, $question_id);
    $question = $question_request['question'];
    $key = $question_request['key'];

    // Just create a new question, if the prior question was new.
    if ($question->isNew()) {
      $new_question = Question::create([
        'bundle' => $form_state->getValue('type'),
        'body' => $form_state->getValue('body'),
        'help' => $form_state->getValue('help'),
        'answers' => $form_state->getValue('answers'),
        'explanation' => $form_state->getValue('explanation'),
        'id' => $form_state->getValue('temp_id'),
      ]);
      $new_question->enforceIsNew();
      $questions[$key] = $new_question;
      $form_state->setValue('question_entities', $questions);
    }

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
  protected function buildRow($question, $temp_id) {
    // Get bundle for question entity.
    /** @var \Drupal\quizzes\QuestionInterface $question */
    $bundle = $this->entityTypeManager
      ->getStorage('question_type')
      ->load($question->bundle())
      ->label();
    $row['#attributes']['class'][] = 'draggable';
    $row['#weight'] = $question->get('weight')->value;
    $row['type'] = [
      '#markup' => $bundle,
    ];
    $row['body'] = [
      '#markup' => $question->get('body')->value,
    ];
    $row['buttons']['delete'] = [
      '#type' => 'button',
      '#attributes' => [
        'class' => ['button--extrasmall align-right'],
        'data-id' => $question->id() ?? $temp_id,
        'data-type' => $question->bundle(),
      ],
      '#value' => $this->t('Delete'),
    ];
    $row['buttons']['edit'] = [
      '#type' => 'button',
      '#attributes' => [
        'class' => ['button--extrasmall align-right'],
        'data-id' => $question->id() ?? $temp_id,
        'data-type' => $question->bundle(),
      ],
      '#value' => $this->t('Edit'),
      '#ajax' => [
        'callback' => '::setQuestionDefaultValues',
        'disable-refocus' => TRUE,
        'event' => 'click',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
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
   * @return array
   *   The requested question and the key in the current entity array.
   */
  private function getRequestedQuestion(array $questions, int $question_id) {
    $question = array_filter($questions, function ($q) use ($question_id) {
      return $q->id() == $question_id;
    });
    return [
      'question' => reset($question),
      'key' => array_key_first($question),
    ];
  }

}
