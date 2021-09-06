<?php

namespace Drupal\academy_lectures\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the lecture entity edit forms.
 */
class LectureForm extends ContentEntityForm {

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
      $this->messenger()->addStatus($this->t('New lecture %label has been created.', $message_arguments));
      $this->logger('academy_lecture')->notice('Created new lecture %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The lecture %label has been updated.', $message_arguments));
      $this->logger('academy_lecture')->notice('Updated new lecture %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.lecture.canonical', ['lecture' => $entity->id()]);
  }

}
