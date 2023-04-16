<?php

namespace Drupal\lectures;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a list controller for the lecture entity type.
 */
final class LectureListBuilder extends EntityListBuilder implements FormInterface {

  /**
   * The entities being listed.
   *
   * @var array
   */
  protected array $entities = [];

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The course storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $courseStorage;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructs a new LectureListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityStorageInterface $course_storage
   *   The course storage class.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    EntityStorageInterface $course_storage,
    FormBuilderInterface $form_builder,
    LanguageManagerInterface $language_manager,
    Request $request
  ) {
    parent::__construct($entity_type, $storage);
    $this->courseStorage = $course_storage;
    $this->formBuilder = $form_builder;
    $this->languageManager = $language_manager;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type
  ) {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('course'),
      $container->get('form_builder'),
      $container->get('language_manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return $this->formBuilder->getForm($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lecture_collection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get query parameter.
    $query_parameter_cr = $this->request->get('cr') ?? NULL;

    // Attach js to hide 'show row weights' buttons.
    $form['#attached']['library'][] = 'academy/hideweightbutton';

    // Load entities and group by courses.
    /** @var \Drupal\lectures\Entity\Lecture[] $lectures */
    $lectures = $this->load();
    $lectures_grouped = [];
    $course_ids = [];
    foreach ($lectures as $lecture) {
      $course_ids[] = $lecture->getParentEntity()->id();
      $lectures_grouped[$lecture->getParentEntity()->id()][$lecture->id()] = $lecture;
    }

    // Find empty courses. This is a little squishy. But just an easy workaround
    // because this is the lecture collection.
    $empty_query = $this->courseStorage->getQuery()->accessCheck(TRUE);
    if (!empty($course_ids)) {
      $empty_query->condition('id', $course_ids, 'NOT IN');
    }
    $empty_query_result = $empty_query->execute();
    foreach ($empty_query_result as $empty_course_id) {
      $course_ids[] = $empty_course_id;
      $lectures_grouped[$empty_course_id] = [];
    }

    // Use this light-weight trick to sort by courses weight.
    $lectures_grouped_sorted = [];
    if (!empty($course_ids)) {
      $sorted_query = $this->courseStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('id', $course_ids, 'IN')
        ->sort('weight')
        ->execute();
      foreach ($sorted_query as $key) {
        $lectures_grouped_sorted[$key] = $lectures_grouped[$key];
      }
    }

    // Attach to entities property.
    $this->entities = $lectures_grouped_sorted;

    // Make tree, so we can order lectures per table.
    $form['course']['#tree'] = TRUE;

    // Iterate lectures by courses and display as details.
    foreach ($lectures_grouped_sorted as $course_id => $lectures) {

      /** @var \Drupal\courses\Entity\Course $course */
      $course = $this->courseStorage->load($course_id);

      $tags = '';
      foreach ($course->get('tags')->getValue() as $tag) {
        $tags .= '<div class="button button--extrasmall is-disabled">' . $tag['value'] . '</div>';
      }
      $disabled_course = '';
      if (!$course->isPublished()) {
        $disabled_course = ' ' . $this->t('(Disabled)');
      }
      $translations = '';
      foreach ($this->languageManager->getLanguages() as $language) {
        if (!$course->hasTranslation($language->getId())) {
          $translations .= '&nbsp;<s class="admin-item__description">' . $language->getId() . '</s>';
        }
      }
      $form['course'][$course_id] = [
        '#type' => 'details',
        '#module_package_listing' => TRUE,
        '#title' => $this->t('Course: @s', ['@s' => $course->getTitle()]) . $disabled_course . $translations,
        '#description' => '<h6>' . $course->get('subtitle')->value . '</h6>
        <div>' . $course->get('description')->value . '</div>' . $tags,
        '#open' => $query_parameter_cr == $course_id,
      ];

      $form['course'][$course_id]['lectures'] = [
        '#type' => 'table',
        '#header' => $this->buildHeader(),
        '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'weight_' . $course_id,
          ],
        ],
      ];

      $delta = count($lectures);

      foreach ($lectures as $lecture) {
        $row = $this->buildRow($lecture, (int) $course_id);
        if (isset($row['weight'])) {
          $row['weight']['#delta'] = $delta;
        }
        $form['course'][$course_id]['lectures'][$lecture->id()] = $row;
      }

      $form['course'][$course_id]['add_lecture'] = [
        '#type' => 'submit',
        '#submit' => ['::redirectAddLecture'],
        '#name' => 'add_lecture_' . $course_id,
        '#attributes' => [
          'class' => ['button--small'],
          'data-id' => $course_id,
        ],
        '#value' => $this->t('+ Add Lecture'),
        '#button_type' => 'primary',
      ];

      $form['course'][$course_id]['submit'] = [
        '#type' => 'submit',
        '#name' => 'submit' . $course_id,
        '#attributes' => [
          'class' => ['button--small'],
          'data-id' => $course_id,
        ],
        '#submit' => ['::submitOrder'],
        '#value' => $this->t('Save Order'),
        '#button_type' => 'secondary',
      ];

      $form['course'][$course_id]['edit_course'] = [
        '#type' => 'submit',
        '#submit' => ['::redirectEditCourse'],
        '#name' => 'edit_course_' . $course_id,
        '#attributes' => [
          'class' => ['button--small'],
          'data-id' => $course_id,
        ],
        '#value' => $this->t('Edit Course'),
        '#button_type' => 'secondary',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Lecture');
    $header['status'] = $this->t('Status');
    $header['translations'] = $this->t('Translation');
    $header['operations'] = [
      'data' => $this->t('Operations'),
      'class' => ['text-align-right'],
    ];
    $header['weight'] = [
      'data' => $this->t('Weight'),
      'class' => ['tabledrag-hide', 'text-align-right'],
    ];
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity, int $course_id = 0) {
    /** @var \Drupal\lectures\Entity\Lecture $entity */
    // Override default values to markup elements.
    $row['#attributes']['class'][] = 'draggable';
    $row['#weight'] = $entity->get('weight')->value;
    // Add content columns.
    $row['title'] = [
      '#markup' => $entity->getTitle(),
    ];
    $row['status'] = [
      '#markup' => $entity->isPublished() ? $this->t('Enabled') : $this->t('Disabled'),
    ];
    $translations = '';
    foreach ($this->languageManager->getLanguages() as $language) {
      if ($language->getId() == $this->languageManager->getDefaultLanguage()->getId()) {
        continue;
      }
      if (!$entity->hasTranslation($language->getId())) {
        $translations .= '<s class="admin-item__description">' . $language->getId() . '</s>&nbsp;';
      }
      else {
        $translations .= $language->getId() . '&nbsp;';
      }
    }
    $row['translations'] = [
      '#markup' => $translations,
    ];
    // Contains operation column.
    $row = $row + parent::buildRow($entity);
    $row['operations']['#wrapper_attributes']['class'] = ['text-align-right'];

    // Add weight column.
    $row['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight for @title', ['@title' => $entity->label()]),
      '#title_display' => 'invisible',
      '#default_value' => $entity->get('weight')->value,
      '#attributes' => ['class' => ['weight_' . $course_id]],
    ];
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Has no main submit.
  }

  /**
   * Changes the order of Lecture entities by course id.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitOrder(array &$form, FormStateInterface $form_state) {
    $course_id = $form_state->getTriggeringElement()['#attributes']['data-id'];
    foreach ($form_state->getValue(['course', $course_id, 'lectures']) as $id => $value) {
      /** @var \Drupal\lectures\Entity\Lecture|null $lecture */
      $lecture = $this->entities[$course_id][$id];
      if (isset($lecture) && $lecture->get('weight')->value != $value['weight']) {
        // Save entity only when its weight was changed.
        $lecture->set('weight', $value['weight']);
        $lecture->save();
      }
    }
    $form_state->setRedirect('entity.lecture.collection', [], [
      'query' => ['cr' => $course_id],
      'fragment' => 'edit-course-' . $course_id,
    ]);
  }

  /**
   * Redirects user to lecture add page.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form_state.
   */
  public function redirectAddLecture(array &$form, FormStateInterface $form_state) {
    $course_id = $form_state->getTriggeringElement()['#attributes']['data-id'];
    $form_state->setRedirect('entity.lecture.add_form', ['course' => $course_id]);
  }

  /**
   * Redirects user to course edit page.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form_state.
   */
  public function redirectEditCourse(array &$form, FormStateInterface $form_state) {
    $course_id = $form_state->getTriggeringElement()['#attributes']['data-id'];
    $form_state->setRedirect('entity.course.edit_form', ['course' => $course_id]);
  }

  /**
   * Loads entity IDs using a pager sorted by the weight.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort('weight');
    return $query->execute();
  }

}
