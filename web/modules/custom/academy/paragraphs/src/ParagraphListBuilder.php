<?php

namespace Drupal\paragraphs;

use Drupal\child_entities\ChildEntityListBuilder;
use Drupal\child_entities\Context\ChildEntityRouteContextTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\ParagraphType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the paragraph entity type.
 */
final class ParagraphListBuilder extends ChildEntityListBuilder implements FormInterface {

  use ChildEntityRouteContextTrait;

  /**
   * The entities being listed.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
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
   * The paragraph type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $paragraphTypeStorage;

  /**
   * Constructs a new LectureListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The child entity route match.
   * @param \Drupal\Core\Entity\EntityStorageInterface $paragraph_type_storage
   *   The paragraph type storage.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    RouteMatchInterface $route_match,
    EntityStorageInterface $paragraph_type_storage,
    FormBuilderInterface $form_builder,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($entity_type, $storage, $route_match);
    $this->paragraphTypeStorage = $paragraph_type_storage;
    $this->formBuilder = $form_builder;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ) {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_route_match'),
      $container->get('entity_type.manager')->getStorage('paragraph_type'),
      $container->get('form_builder'),
      $container->get('language_manager')
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
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['bundle'] = $this->t('Type');
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
  public function buildRow(EntityInterface $entity): array {

    // Get bundle for paragraph entity.
    /** @var \Drupal\paragraphs\Entity\Paragraph $entity */
    $bundle = $this->paragraphTypeStorage->load($entity->bundle());

    if (!($bundle instanceof ParagraphType)) {
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
    $translations = '';
    if ($entity->bundle() !== 'evaluation' && $entity->bundle() !== 'questionnaire') {
      foreach ($this->languageManager->getLanguages() as $language) {
        if ($language->getId() === $this->languageManager->getDefaultLanguage()->getId()) {
          continue;
        }
        if (!$entity->hasTranslation($language->getId())) {
          $translations .= '<s class="admin-item__description">' . $language->getId() . '</s>&nbsp;';
        }
        else {
          $translations .= $language->getId() . '&nbsp;';
        }
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
      '#attributes' => ['class' => ['weight']],
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paragraph_collection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Attach js to hide 'show row weights' buttons.
    $form['#attached']['library'][] = 'academy/hideweightbutton';

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
    $form['actions']['add_paragraph'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Add Paragraph'),
      '#url' => Url::fromRoute('entity.paragraph.add_page', [
        'lecture' => $this->getParentEntityFromRoute('lecture')->id(),
        'course' => $this->getParentEntityFromRoute('course')->id(),
      ]),
      '#attributes' => [
        'class' => ['button button--small button--primary'],
      ],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Order'),
      '#button_type' => 'secondary',
      '#attributes' => [
        'class' => ['button--small'],
      ],
    ];
    $form['actions']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Lecture'),
      '#url' => Url::fromRoute('entity.lecture.edit_form', [
        'lecture' => $this->getParentEntityFromRoute('lecture')->id(),
        'course' => $this->getParentEntityFromRoute('course')->id(),
      ]),
      '#attributes' => [
        'class' => ['button button--small'],
      ],
    ];
    $form['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to Courses'),
      '#url' => Url::fromRoute('entity.lecture.collection', [], [
        'query' => ['cr' => $this->getParentEntityFromRoute('course')->id()],
        'fragment' => 'edit-course-' . $this->getParentEntityFromRoute('course')->id(),
      ]),
      '#attributes' => [
        'class' => ['button button--small'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // No validation.
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    foreach ($form_state->getValue('entities') as $id => $value) {
      /** @var \Drupal\paragraphs\Entity\Paragraph|null $paragraph */
      $paragraph = $this->entities[$id];
      if (isset($paragraph) && $paragraph->get('weight')->value != $value['weight']) {
        // Save entity only when its weight was changed.
        $paragraph->set('weight', $value['weight']);
        $paragraph->save();
      }
    }
    parent::submitForm($form, $form_state);
  }

}
