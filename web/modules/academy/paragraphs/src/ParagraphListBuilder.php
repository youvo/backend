<?php

namespace Drupal\paragraphs;

use Drupal\child_entities\ChildEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\paragraphs\Entity\ParagraphType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the paragraph entity type.
 */
class ParagraphListBuilder extends ChildEntityListBuilder implements FormInterface {

  /**
   * The entities being listed.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
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
   * Constructs a new ParagraphListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The Child Entity Route Match.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RouteMatchInterface $route_match, DateFormatterInterface $date_formatter, RedirectDestinationInterface $redirect_destination) {
    parent::__construct($entity_type, $storage, $route_match);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_route_match'),
      $container->get('date.formatter'),
      $container->get('redirect.destination'),
    );
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
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['bundle'] = $this->t('Type');
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
    // Get bundle for paragraph entity.
    /** @var \Drupal\paragraphs\ParagraphInterface $entity */
    $bundle = \Drupal::entityTypeManager()
      ->getStorage('paragraph_type')
      ->load($entity->bundle());

    if (!($bundle instanceof ParagraphType)) {
      \Drupal::logger('paragraphs')
        ->warning('Paragraphs Collection: Could not fetch bundle for entity type %try.', ['%try' => $entity->bundle()]);
      return [];
    }

    // Override default values to markup elements.
    $row['#attributes']['class'][] = 'draggable';
    $row['#weight'] = $entity->get('weight')->value;
    // Add content columns.
    $row['name'] = [
      '#markup' => $entity->getTitle(),
    ];
    $row['bundle'] = [
      '#markup' => $bundle->label(),
    ];
    // Contains operation column.
    // @todo Fix route entity parameters.
    // $row = $row + parent::buildRow($entity);
    $row['operations']['data'] = [];
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
  public function getFormId() {
    return 'paragraph_collection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['entities'] = [
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

    $this->entities = $this->load();
    $delta = count($this->entities);

    foreach ($this->entities as $entity) {
      $row = $this->buildRow($entity);
      if (isset($row['weight'])) {
        $row['weight']['#delta'] = $delta;
      }
      $form['entities'][$entity->id()] = $row;
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('entities') as $id => $value) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
      $paragraph = $this->entities[$id];
      if (isset($paragraph) && $paragraph->get('weight')->value != $value['weight']) {
        // Save entity only when its weight was changed.
        $paragraph->set('weight', $value['weight']);
        $paragraph->save();
      }
    }
    parent::submitForm($form, $form_state);
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

}
