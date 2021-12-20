<?php

namespace Drupal\academy;

use Drupal\Core\Entity\ContentEntityInterface;

trait TranslationFormButtonsTrait {

  /**
   * Require t.
   */
  abstract public function t($string, array $args = [], array $options = []);

  /**
   * Amends translation buttons container to forms.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function addTranslationButtons(array &$form, ContentEntityInterface $entity) {

    $form['translations'] = [
      '#type' => 'container',
      '#weight' => -10,
    ];

    $form['translations']['overview'] = [
      '#type' => 'link',
      '#title' => $this->t('Translations'),
      '#url' => $entity->toUrl('drupal:content-translation-overview'),
      '#attributes' => [
        'class' => ['button button--small'],
      ],
    ];

  }
}
