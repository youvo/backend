<?php

namespace Drupal\lectures;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the lecture entity type.
 */
class LectureListBuilder extends EntityListBuilder implements FormInterface {

  /**
   * The entities being listed.
   *
   * @var array
   */
  protected $entities = [];

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new LectureListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter, RedirectDestinationInterface $redirect_destination) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('redirect.destination')
    );
  }

  /**
   * Returns the form builder.
   *
   * @return \Drupal\Core\Form\FormBuilderInterface
   *   The form builder.
   */
  protected function formBuilder() {
    if (!$this->formBuilder) {
      $this->formBuilder = \Drupal::formBuilder();
    }
    return $this->formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return $this->formBuilder()->getForm($this);
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

    // Load entities and group by courses.
    /** @var \Drupal\lectures\Entity\Lecture[] $lectures */
    $lectures = $this->load();
    $lectures_grouped = [];
    foreach ($lectures as $lecture) {
      $lectures_grouped[$lecture->getParentEntity()->id()][$lecture->id()] = $lecture;
    }
    $this->entities = $lectures_grouped;

    foreach ($lectures_grouped as $course_id => $lectures) {

      /** @var \Drupal\courses\Entity\Course $course */
      $course = \Drupal::entityTypeManager()->getStorage('course')->load($course_id);

      $form['course'][$course_id] = [
        '#type' => 'details',
        '#module_package_listing' => TRUE,
        '#title' => 'Course: ' . $course->getTitle(),
        '#description' => '<div class="leader">' . $course->get('description')->value . '</div>',
        '#open' => FALSE,
      ];

      $form['course'][$course_id]['lectures'] = [
        '#type' => 'table',
        '#header' => $this->buildHeader(),
        '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'weight',
          ],
        ],
      ];

      $delta = count($lectures);

      foreach ($lectures as $lecture) {
        $row = $this->buildRow($lecture);
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
        '#submit' => [],
        '#value' => $this->t('Save Order'),
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
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\lectures\Entity\Lecture $entity */
    // Override default values to markup elements.
    $row['#attributes']['class'][] = 'draggable';
    $row['#weight'] = $entity->get('weight')->value;
    // Add content columns.
    $row['title'] = [
      '#markup' => $entity->getTitle(),
    ];
    $row['status'] = [
      '#markup' => $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled'),
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
      '#attributes' => ['class' => ['weight']],
    ];
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No validation.
  }

  /**
   * {@inheritdoc}
   *
   * @todo Adjust for different entities array.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('entities') as $id => $value) {
      /** @var \Drupal\lectures\Entity\Lecture $lecture */
      $lecture = $this->entities[$id];
      if (isset($lecture) && $lecture->get('weight')->value != $value['weight']) {
        // Save entity only when its weight was changed.
        $lecture->set('weight', $value['weight']);
        $lecture->save();
      }
    }
  }

  /**
   * Submit handler that redirects user to lecture add page.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form_state.
   */
  public function redirectAddLecture(array &$form, FormStateInterface $form_state) {
    $course_id = $form_state->getTriggeringElement()['#attributes']['data-id'];
    $redirect = Url::fromRoute('entity.lecture.add_form', ['course' => $course_id]);
    $form_state->setRedirectUrl($redirect);
  }

}
