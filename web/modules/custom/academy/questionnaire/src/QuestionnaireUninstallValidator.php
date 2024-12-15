<?php

namespace Drupal\questionnaire;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentUninstallValidator;

/**
 * Prevents uninstallation of questionnaire module if content is present.
 */
class QuestionnaireUninstallValidator extends ContentUninstallValidator {

  /**
   * {@inheritdoc}
   *
   * @todo Offer functionality to bulk delete questionnaire content entities.
   */
  public function validate($module): array {

    $reasons = [];

    if ($module === 'questionnaire') {
      try {
        $paragraphs = $this->entityTypeManager
          ->getStorage('paragraph')
          ->loadByProperties(['bundle' => 'questionnaire']);
        if (count($paragraphs) > 0) {
          $reasons[] = $this->t('There is content for the paragraph type: Questionnaire.');
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        $reasons[] = $this->t('Unable to get questionnaire paragraph storage.');
      }
    }

    return $reasons;
  }

}
