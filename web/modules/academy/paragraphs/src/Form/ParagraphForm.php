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

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New paragraph %label has been created.', $message_arguments));
      $this->logger('academy_paragraph')->notice('Created new paragraph %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The paragraph %label has been updated.', $message_arguments));
      $this->logger('academy_paragraph')->notice('Updated new paragraph %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.paragraph.canonical', ['paragraph' => $entity->id()]);
  }

}
