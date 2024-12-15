<?php

namespace Drupal\lectures\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\youvo\TranslationFormButtonsTrait;

/**
 * Form controller for the lecture entity edit forms.
 */
class LectureForm extends ContentEntityForm {

  use TranslationFormButtonsTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\lectures\Entity\Lecture $lecture */
    $lecture = $this->getEntity();

    if (!$lecture->isNew() &&
      $lecture->getEntityType()->hasLinkTemplate('drupal:content-translation-overview')) {
      $this->addTranslationButtons($form, $lecture);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {

    // Save entity.
    $result = parent::save($form, $form_state);
    $arguments = ['%label' => $this->entity->label()];

    if ($result === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New lecture %label has been created.', $arguments));
      $this->logger('academy')->notice('Created new lecture %label', $arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The lecture %label has been updated.', $arguments));
    }

    return $result;
  }

}
