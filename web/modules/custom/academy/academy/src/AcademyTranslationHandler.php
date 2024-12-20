<?php

namespace Drupal\academy;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * AcademyTranslationHandler extends functionality of ContentTranslationHandler.
 *
 * @todo Should be moved to a more general namespace. Also, see youvo.module.
 */
class AcademyTranslationHandler extends ContentTranslationHandler {

  /**
   * Alters forms for translatable entities.
   *
   * Hide content translation field. Add buttons on top of form for
   * translations. These are quick-links to the form of the respective
   * language. The form container 'translations' is defined in the
   * `TranslationFormButtonsTrait`.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity): void {
    parent::entityFormAlter($form, $form_state, $entity);

    // Hide content translation field.
    $form['content_translation']['#access'] = FALSE;

    // Amend translation buttons container.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $translations = $entity->getTranslationLanguages();
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $form_langcode = $form_object->getFormLangcode($form_state);

    foreach ($translations as $translation) {
      $langcode = $translation->getId();
      if ($langcode !== $form_langcode) {
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
   * Delete warning, which is displayed when untranslatable fields are excluded
   * from display.
   *
   * @phpstan-ignore-next-line Parent does not define proper types.
   */
  public function entityFormSharedElements($element, FormStateInterface $form_state, $form): array {
    $element = parent::entityFormSharedElements($element, $form_state, $form);
    $this->messenger->deleteByType('warning');
    return $element;
  }

}
