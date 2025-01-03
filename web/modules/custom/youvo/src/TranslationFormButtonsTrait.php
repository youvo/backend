<?php

namespace Drupal\youvo;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Trait to add translations container and button to form element.
 */
trait TranslationFormButtonsTrait {

  /**
   * Require t().
   */
  abstract public function t($string, array $args = [], array $options = []);

  /**
   * Amends translation buttons container to forms.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function addTranslationButtons(array &$form, ContentEntityInterface $entity): void {

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
