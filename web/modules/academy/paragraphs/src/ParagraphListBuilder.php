<?php

namespace Drupal\paragraphs;

use Drupal\child_entities\ChildEntityListBuilder;
use Drupal\child_entities\Context\ChildEntityRouteContextTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\paragraphs\Entity\ParagraphType;

/**
 * Provides a list controller for the paragraph entity type.
 */
class ParagraphListBuilder extends ChildEntityListBuilder implements FormInterface {

  use ChildEntityRouteContextTrait;

  /**
   * The entities being listed.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Returns the language manager service.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  protected function languageManager() {
    if (!$this->languageManager) {
      $this->languageManager = \Drupal::languageManager();
    }
    return $this->languageManager;
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
  public function buildRow(EntityInterface $entity) {
    // Get bundle for paragraph entity.
    /** @var \Drupal\paragraphs\Entity\Paragraph $entity */
    $bundle = '';
    try {
      $bundle = \Drupal::entityTypeManager()
        ->getStorage('paragraph_type')
        ->load($entity->bundle());
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('academy')
        ->error('An error occurred while loading paragraph types. %type: @message in %function (line %line of %file).', $variables);
    }

    if (!($bundle instanceof ParagraphType)) {
      \Drupal::logger('academy')
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
    $translations = '';
    foreach ($this->languageManager()->getLanguages() as $language) {
      if ($language->getId() == $this->languageManager()->getDefaultLanguage()->getId()) {
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
      '#attributes' => ['class' => ['weight']],
    ];
    return $row;
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
