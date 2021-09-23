<?php

namespace Drupal\paragraphs\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

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
      $this->logger('paragraphs')->notice('Updated new paragraph %label.', $arguments);
    }

    $form_state->setRedirect('entity.paragraph.collection', ['lecture' => $paragraph->getParentEntity()->id()]);
  }

}
