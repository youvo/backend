<?php

namespace Drupal\paragraphs\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Form controller for the paragraph entity edit forms.
 */
class ParagraphForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function form(array $form, FormStateInterface $form_state) {

    // Build parent form.
    $form = parent::form($form, $form_state);

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $this->getEntity();

    if (!$paragraph->isNew() &&
      $paragraph->getEntityType()->hasLinkTemplate('drupal:content-translation-overview') &&
      $paragraph->bundle() != 'evaluation' &&
      $paragraph->bundle() != 'questionnaire') {
      $form['translations'] = [
        '#type' => 'container',
        '#weight' => -10,
      ];

      $form['translations']['overview'] = [
        '#type' => 'link',
        '#title' => $this->t('Translations'),
        '#url' => $paragraph->toUrl('drupal:content-translation-overview'),
        '#attributes' => [
          'class' => ['button button--small'],
        ],
      ];
    }

    // Add answers multi value form element.
    if ($paragraph->bundle() == 'stats') {

      // Attach js to hide 'show row weights' buttons.
      $form['#attached']['library'][] = 'academy/hideweightbutton';

      // Load default values for answers.
      $default_answers = [];
      $stats = $paragraph->get('list')->getValue();
      $description = $paragraph->get('description')->getValue();
      foreach (array_keys($stats) as $delta) {
        $default_answers[] = [
          'stat' => $stats[$delta]['value'] ?? NULL,
          'description' => $description[$delta]['value'] ?? NULL,
        ];
      }

      // Attach answers multi value form element.
      $form['multistats'] = [
        '#title' => $this->t('Stats'),
        '#type' => 'multivalue',
        '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
        '#add_more_label' => $this->t('Add stat'),
        '#default_value' => $default_answers,
        'stat' => [
          '#type' => 'textfield',
          '#title' => $this->t('Stat'),
        ],
        'description' => [
          '#type' => 'textarea',
          '#rows' => 2,
          '#title' => $this->t('Description'),
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
    /** @var \Drupal\child_entities\ChildEntityInterface $paragraph */
    $paragraph = $this->getEntity();

    // Add values from multistats form element.
    if ($paragraph->bundle() == 'stats') {
      $stats = $form_state->getValue('multistats');
      $paragraph->set('list', []);
      $paragraph->set('description', []);
      foreach ($stats as $stat) {
        if (!empty($stat['stat']) || !empty($stat['description'])) {
          $paragraph->get('list')->appendItem($stat['stat']);
          $paragraph->get('description')->appendItem($stat['description']);
        }
      }
    }

    // Save entity.
    $result = parent::save($form, $form_state);

    // Save paragraph.
    $paragraph->save();

    // Add status and logger messages.
    $arguments = ['%label' => $paragraph->label()];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New paragraph %label has been created.', $arguments));
      $this->logger('academy')->notice('Created new paragraph %label', $arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The paragraph %label has been updated.', $arguments));
    }

    /** @var \Drupal\child_entities\ChildEntityInterface $lecture */
    $lecture = $paragraph->getParentEntity();
    $form_state->setRedirect('entity.paragraph.collection', [
      'lecture' => $lecture->id(),
      'course' => $lecture->getParentEntity()->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {

    // Get entitys actions.
    $actions = parent::actions($form, $form_state);

    // Add an abort button.
    /** @var \Drupal\child_entities\ChildEntityInterface $paragraph */
    /** @var \Drupal\child_entities\ChildEntityInterface $lecture */
    $paragraph = $this->getEntity();
    $lecture = $paragraph->getParentEntity();
    $url = Url::fromRoute('entity.paragraph.collection', [
      'lecture' => $lecture->id(),
      'course' => $lecture->getParentEntity()->id(),
    ]);
    $actions['submit']['#value'] = $this->t('Save %language', ['%language' => $paragraph->language()->getName()]);
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
