<?php

namespace Drupal\feedback\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the feedback entity edit forms.
 */
class FeedbackForm extends ContentEntityForm {

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
      $this->messenger()->addStatus($this->t('New feedback %label has been created.', $message_arguments));
      $this->logger('feedback')->notice('Created new feedback %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The feedback %label has been updated.', $message_arguments));
      $this->logger('feedback')->notice('Updated new feedback %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.feedback.canonical', ['feedback' => $entity->id()]);
  }

}
