<?php

namespace Drupal\logbook\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\logbook\LogTextInterface;
use Drupal\youvo\TranslationFormButtonsTrait;

/**
 * Form controller for the log entity edit forms.
 *
 * @property \Drupal\logbook\LogTextInterface $entity
 */
class LogTextForm extends ContentEntityForm {

  use TranslationFormButtonsTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function form(array $form, FormStateInterface $form_state): array {

    // Build parent form.
    $form = parent::form($form, $form_state);

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $this->entity->getText(),
    ];

    $form['public_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Public Text'),
      '#default_value' => $this->entity->getPublicText(),
    ];

    $log_text = $this->getEntity();
    if (!$log_text instanceof LogTextInterface) {
      return $form;
    }

    if (
      !$log_text->isNew() &&
      $log_text->getEntityType()->hasLinkTemplate('drupal:content-translation-overview')
    ) {
      $this->addTranslationButtons($form, $log_text);
    }

    $form_state->setRedirect('entity.log_pattern.edit_form', [
      'log_pattern' => $log_text->getParentEntity()->id(),
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {

    $result = parent::save($form, $form_state);
    $form_state->setRedirect('entity.log_pattern.edit_form', [
      'log_pattern' => $this->entity->getParentEntity()->id(),
    ]);

    return $result;
  }

}
