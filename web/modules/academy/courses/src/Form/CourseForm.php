<?php

namespace Drupal\courses\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the course entity edit forms.
 */
class CourseForm extends ContentEntityForm {

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
      $this->messenger()->addStatus($this->t('New course %label has been created.', $message_arguments));
      $this->logger('courses')->notice('Created new course %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The course %label has been updated.', $message_arguments));
      $this->logger('courses')->notice('Updated new course %label.', $logger_arguments);
    }

    if ($this->moduleHandler->moduleExists('lectures')) {
      $form_state->setRedirect('entity.lecture.collection');
    }
    else {
      $form_state->setRedirect('admin.content');
    }

  }

}
