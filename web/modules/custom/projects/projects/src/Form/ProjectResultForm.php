<?php

namespace Drupal\projects\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the project result entity edit forms.
 */
class ProjectResultForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\child_entities\ChildEntityInterface $entity */
    $entity = $this->getEntity();
    $result = $entity->save();

    $message_arguments = ['%label' => $this->entity->label()];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New project result %label has been created.', $message_arguments));
    }
    else {
      $this->messenger()->addStatus($this->t('The project result %label has been updated.', $message_arguments));
    }

    $form_state->setRedirect('entity.project.canonical', ['node' => $entity->getParentEntity()->id()]);

    return $result;
  }

}
