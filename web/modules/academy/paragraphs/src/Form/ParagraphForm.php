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
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save entity.
    $result = parent::save($form, $form_state);

    // Load populated entity.
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $this->getEntity();

    // Add status and logger messages.
    $link = $paragraph->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $paragraph->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New paragraph %label has been created.', $message_arguments));
      $this->logger('paragraphs')->notice('Created new paragraph %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The paragraph %label has been updated.', $message_arguments));
      $this->logger('paragraphs')->notice('Updated new paragraph %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.paragraph.collection', ['lecture' => $paragraph->getParentEntity()->id()]);
  }

}
