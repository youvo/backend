<?php

namespace Drupal\academy;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * AcademyTranslationHandler extends functionality of ContentTranslationHandler.
 */
class AcademyTranslationHandler extends ContentTranslationHandler {

  /**
   * Hide content translation field.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    parent::entityFormAlter($form, $form_state, $entity);
    $form['content_translation']['#access'] = FALSE;

    /** @var \Drupal\child_entities\ChildEntityInterface|\Drupal\academy\AcademicFormatInterface $entity */
    $translations = $entity->getTranslationLanguages();
    $form_object = $form_state->getFormObject();
    $form_langcode = $form_object->getFormLangcode($form_state);

    foreach ($translations as $translation) {
      $langcode = $translation->getId();
      if ($langcode != $form_langcode) {
        $form['translations'][$langcode] = [
          '#type' => 'link',
          '#title' => $translation->getName(),
          '#url' => $entity->toUrl('edit-form', ['language' => $translation]),
          '#attributes' => [
            'class' => ['button button--small'],
          ],
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Delete warning.
   */
  public function entityFormSharedElements($element, FormStateInterface $form_state, $form) {
    $element = parent::entityFormSharedElements($element, $form_state, $form);
    $this->messenger->deleteByType('warning');
    return $element;
  }

}
