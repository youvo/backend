<?php

namespace Drupal\questionnaire\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\paragraphs\Form\ParagraphForm;
use Drupal\questionnaire\Entity\Question;
use Drupal\questionnaire\Entity\Questionnaire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the paragraph entity with questionnaire edit forms.
 */
class ParagraphQuestionnaireForm extends ParagraphForm {

  use MessengerTrait;
  use QuestionProcessTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    if ($this->entity instanceof Questionnaire) {
      $this->attachQuestionnaireForm($form, $form_state);
    }

    return $form;
  }

  /**
   * Adds form elements for questionnaire.
   */
  protected function attachQuestionnaireForm(&$form, FormStateInterface $form_state) {

    // Delete all queued messages.
    $this->messenger()->deleteAll();

    // Attach js to hide 'show row weights' buttons.
    $form['#attached']['library'][] = 'academy/hideweightbutton';
    $form['#attached']['library'][] = 'questionnaire/hidesubmitbutton';

    // Hide unused form elements.
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

    // Get all questions that have this questionnaire paragraph as a parent.
    $questions = [];
    try {
      $questions_storage = $this->entityTypeManager
        ->getStorage('question');
      $questions_query = $questions_storage->getQuery()
        ->condition('paragraph', $this->entity->id())
        ->sort('weight')
        ->execute();
      $questions = $questions_storage->loadMultiple($questions_query);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger('academy')
        ->error('An error occurred while loading questions. %type: @message in %function (line %line of %file).', $variables);
    }

    $form['questions']['question_entities'] = [
      '#type' => 'value',
      '#default_value' => $questions,
    ];

    // Determine delta for the weight distribution.
    $delta = count($questions);

    // Fill the table with row entries.
    foreach ($questions as $question) {
      $row = $this->buildRow($question, $form_state->hasValue('type'));
      if (isset($row['weight'])) {
        $row['weight']['#delta'] = $delta;
      }
      $form['questions']['table'][$question->id()] = $row;
    }

    // We load the different question types and append buttons to add a
    // question to the current questionnaire.
    $form['questions']['add_question'] = [
      '#type' => 'fieldset',
      '#weight' => '99',
      '#access' => !$form_state->hasValue('type'),
    ];

    $question_types = [];
    try {
      $question_types = $this->entityTypeManager
        ->getStorage('question_type')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger('academy')
        ->error('An error occurred while loading question types. %type: @message in %function (line %line of %file).', $variables);
    }

    foreach ($question_types as $question_type) {
      $form['questions']['add_question'][$question_type->id()] = [
        '#type' => 'submit',
        '#submit' => ['::showQuestionFieldset'],
        '#value' => '+ ' . $question_type->label(),
        '#attributes' => [
          'data-type' => $question_type->id(),
        ],
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
          ['type'],
        ],
      ];
    }

    // We use hidden elements here, because multi-value form element requires
    // the $form element to be build in order to generate more fields.
    $answers_hidden = FALSE;
    $hidden = ['class' => ['hidden']];
    if ($form_state->hasValue('type') &&
      ($form_state->getValue('type') == 'textarea' ||
      $form_state->getValue('type') == 'textfield') ||
      $form_state->getValue('type') == 'task') {
      $answers_hidden = TRUE;
    }

    // Provide containers to transport id and type between Ajax responses.
    // This field gets populated by the ajax response.
    $form['questions']['current_id'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];
    $form['questions']['type'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#value' => $form_state->getValue('type'),
    ];

    // We add form elements to fill the question entity in the submit-handler.
    // These correspond to the fields in a question entity.
    $form['questions']['elements'] = [
      '#prefix' => '<div id="error-wrapper"></div>',
      '#type' => 'fieldset',
      '#attributes' => $form_state->hasValue('type') ? [] : $hidden,
    ];

    // Manually define the form elements for questions. We have to ensure that
    // these questions represent the fields of a question.
    // @todo Find a way to resolve widget to form array from fieldManager.
    $form['questions']['elements']['body'] = [
      '#title' => $this->t('Question'),
      '#type' => 'textarea',
      '#rows' => 2,
    ];

    $form['questions']['elements']['help'] = [
      '#title' => $this->t('Help Text'),
      '#type' => 'textarea',
      '#description' => $this->t('Further explanation to the question.'),
      '#rows' => 3,
    ];

    $form['questions']['elements']['multi_answers'] = [
      '#title' => $this->t('Answers'),
      '#type' => 'multivalue',
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#description' => $this->t('Specify the potential answers. Check if they are correct. Only one for radios question!'),
      '#add_more_label' => $this->t('Add answer'),
      '#attributes' => $answers_hidden ? $hidden : [],
      '#disabled' => $answers_hidden,
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
      '#rows' => 3,
    ];

    $form['questions']['elements']['required'] = [
      '#title' => $this->t('Required'),
      '#type' => 'checkbox',
      '#description' => $this->t('This question has to be answered in order to complete current lecture.'),
    ];

    // Trigger a 'submit' for the form elements in the container and create
    // a question entity. Then represent such question entity in the table
    // above.
    $form['questions']['elements']['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Question'),
      '#validate' => ['::validateQuestion'],
      '#submit' => ['::submitCreateQuestion'],
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
        ['body'],
        ['help'],
        ['options'],
        ['multi_answers'],
        ['explanation'],
        ['required'],
        ['elements'],
        ['title'],
      ],
    ];

    // We can also abort current creation and rebuild the form as it was
    // before.
    $form['questions']['elements']['abort'] = [
      '#type' => 'submit',
      '#submit' => ['::submitAbortQuestion'],
      '#value' => $this->t('Abort'),
      '#ajax' => [
        'callback' => '::rebuildAjax',
        'wrapper' => 'questions-wrapper',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];

    // This is a little button to confirm the deletion.
    $form['questions']['confirm_delete'] = [
      '#type' => 'submit',
      '#submit' => ['::submitDeleteQuestion'],
      '#attributes' => ['class' => ['button--extrasmall button--danger align-right visually-hidden']],
      '#value' => $this->t('Confirm Deletion'),
      '#ajax' => [
        'callback' => '::rebuildAjax',
        'wrapper' => 'questions-wrapper',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
      '#limit_validation_errors' => [
        ['current_id'],
        ['question_entities'],
        ['title'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {

    // Save Questionnaire as Paragraph.
    $result = parent::save($form, $form_state);

    if ($this->entity instanceof Questionnaire) {
      // Save the new and persistent questions with weights.
      $questions = $form_state->getValue('question_entities');
      $table = $form_state->getValue('table');
      foreach ($questions as $question) {
        /** @var \Drupal\questionnaire\Entity\Question $question */
        $weight = $table[$question->id()]['weight'] ?? 0;
        $question->set('weight', $weight);
        $question->set('paragraph', $this->entity->id());
        $question->save();
      }
    }

    return $result;
  }

  /**
   * Adds a question form to the questionnaire.
   */
  public function showQuestionFieldset(array &$form, FormStateInterface $form_state) {
    $current_input = $form_state->getUserInput();
    unset($current_input['body']);
    unset($current_input['help']);
    unset($current_input['explanation']);
    unset($current_input['multi_answers']);
    unset($current_input['required']);
    $form_state->setUserInput($current_input);
    $form_state->setValue('type', $form_state->getTriggeringElement()['#attributes']['data-type']);
    $form_state->setRebuild();
  }

  /**
   * Rebuilds the form or delivers form errors from validation.
   */
  public function rebuildAjax(array $form, FormStateInterface $form_state) {
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
   * Adds a question form to the questionnaire.
   */
  public function submitAbortQuestion(array &$form, FormStateInterface $form_state) {
    $form_state->unsetValue('type');
    $form_state->setRebuild();
  }

  /**
   * Creates and saves new question.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitCreateQuestion(array &$form, FormStateInterface $form_state) {

    // The paragraph might be new. We save here to ensure that an ID is present.
    $this->entity->set('title', $form_state->getValue('title'));
    $this->entity->save();

    // Create new question from form input.
    $new_question = Question::create([
      'bundle' => $form_state->getValue('type'),
      'body' => $form_state->getValue('body'),
      'help' => $form_state->getValue('help'),
      'explanation' => $form_state->getValue('explanation'),
      'paragraph' => $this->entity->id(),
      'required' => $form_state->getValue('required'),
    ]);

    // Add values from multi_answers form element.
    if ($form_state->getValue('type') == 'checkboxes' ||
      $form_state->getValue('type') == 'radios') {
      $this->populateMultiAnswerToQuestion($new_question, $form_state);
    }

    // Append new question to paragraph.
    $new_question->save();
    $this->entity->get('questions')->appendItem($new_question->id());
    $this->entity->save();

    $form_state->unsetValue('type');
    $form_state->setRebuild();
  }

  /**
   * Populates hidden fields for deletion.
   */
  public function prepareDeleteQuestion(array &$form, FormStateInterface $form_state) {

    // Get question id from data attribute.
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
   * Remove questions and rebuild.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitDeleteQuestion(array &$form, FormStateInterface $form_state) {

    // Get the requested question.
    $question_id = $form_state->getValue('current_id');
    $questions = $form_state->getValue('question_entities');
    $question = $this->getRequestedQuestion($questions, $question_id);

    // If the question is not new, we need to delete the question and rebuild
    // the reference in paragraph entity.
    if (!$question->isNew()) {
      $questions = array_map(fn($q) => ['target_id' => $q], array_keys($questions));
      $key = array_search($question->id(), array_column($questions, 'target_id'));
      $question->delete();
      unset($questions[$key]);
      $this->entity->set('title', $form_state->getValue('title'));
      $this->entity->set('questions', $questions);
      $this->entity->save();
    }

    $form_state->unsetValue('current_id');
    $form_state->setRebuild();
  }

  /**
   * Gives header for table of questions.
   */
  protected function buildHeader() {
    $header['type'] = $this->t('Type');
    $header['body'] = $this->t('Question');
    $header['translations'] = $this->t('Translation');
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
    /** @var \Drupal\questionnaire\Entity\Question $question */
    $bundle = '';
    try {
      $bundle = $this->entityTypeManager
        ->getStorage('question_type')
        ->load($question->bundle())
        ->label();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger('academy')
        ->error('An error occurred while loading question types. %type: @message in %function (line %line of %file).', $variables);
    }
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
    $translations = '';
    foreach ($this->languageManager->getLanguages() as $language) {
      if ($language->getId() == $this->languageManager->getDefaultLanguage()->getId()) {
        continue;
      }
      if (!$question->hasTranslation($language->getId())) {
        $translations .= '<s class="admin-item__description">' . $language->getId() . '</s>&nbsp;';
      }
      else {
        $translations .= $language->getId() . '&nbsp;';
      }
    }
    $row['translations'] = [
      '#markup' => $translations,
    ];
    $row['buttons']['delete'] = [
      '#type' => 'button',
      '#prefix' => '<div id="buttons-' . $question->id() . '">',
      '#attributes' => [
        'class' => ['button--extrasmall align-right'],
        'data-id' => $question->id(),
      ],
      '#value' => $this->t('Delete'),
      '#name' => 'delete-' . $question->id(),
      '#disabled' => $buttons_disabled,
      '#ajax' => [
        'callback' => '::prepareDeleteQuestion',
        'event' => 'click',
        'effect' => 'none',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];
    /** @var \Drupal\child_entities\ChildEntityInterface $questionnaire */
    $questionnaire = $this->entity;
    /** @var \Drupal\child_entities\ChildEntityInterface $lecture */
    $lecture = $questionnaire->getParentEntity();
    $is_disabled = $buttons_disabled ? ' is-disabled' : '';
    $url = !$buttons_disabled ?
      Url::fromRoute('entity.question.edit_form', [
        'paragraph' => $questionnaire->id(),
        'lecture' => $lecture->id(),
        'course' => $lecture->getParentEntity()->id(),
        'question' => $question->id(),
      ]) :
      Url::fromUserInput('#');
    $row['buttons']['edit'] = [
      '#type' => 'link',
      '#suffix' => '</div>',
      '#url' => $url,
      '#attributes' => [
        'class' => ['button button--extrasmall align-right' . $is_disabled],
      ],
      '#title' => $this->t('Edit'),
      '#name' => 'edit-' . $question->id(),
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
   * @return \Drupal\questionnaire\Entity\Question
   *   The requested question.
   */
  protected function getRequestedQuestion(array $questions, int $question_id) {
    $question = array_filter($questions, function ($q) use ($question_id) {
      return $q->id() == $question_id;
    });
    return reset($question);
  }

}
