<?php

namespace Drupal\paragraphs\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\paragraphs\ParagraphFormInfoTrait;

/**
 * Form controller for the paragraph entity edit forms.
 */
class ParagraphForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save entity.
    $result = parent::save($form, $form_state);

    // Add status and logger messages.
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $this->getEntity();
    $arguments = ['%label' => $paragraph->label()];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New paragraph %label has been created.', $arguments));
      $this->logger('paragraphs')->notice('Created new paragraph %label', $arguments);
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
