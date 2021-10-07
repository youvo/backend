<?php

namespace Drupal\lectures\Form;

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

    // Save entity.
    $result = parent::save($form, $form_state);
    $arguments = ['%label' => $this->entity->label()];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New lecture %label has been created.', $arguments));
      $this->logger('lectures')->notice('Created new lecture %label', $arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The lecture %label has been updated.', $arguments));
      $this->logger('lectures')->notice('Updated new lecture %label.', $arguments);
    }

    /** @var \Drupal\lectures\Entity\Lecture $lecture */
    $lecture = $this->entity;
    $course = $lecture->getParentEntity();
    $form_state->setRedirect('entity.paragraph.collection', [
      'course' => $course->id(),
      'lecture' => $lecture->id(),
    ]);
  }

}
