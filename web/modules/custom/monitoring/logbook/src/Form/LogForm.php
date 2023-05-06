<?php

namespace Drupal\logbook\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the log entity edit forms.
 */
class LogForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();

    $message_arguments = ['%label' => $this->entity->label()];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New log %label has been created.', $message_arguments));
    }
    else {
      $this->messenger()->addStatus($this->t('The log %label has been updated.', $message_arguments));
    }

    $form_state->setRedirect('entity.log.canonical', ['log' => $entity->id()]);

    return $result;
  }

}
