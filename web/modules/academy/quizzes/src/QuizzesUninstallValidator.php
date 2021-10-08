<?php

namespace Drupal\quizzes;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentUninstallValidator;

/**
 * Prevents uninstallation of quiz module if content is present.
 */
class QuizzesUninstallValidator extends ContentUninstallValidator {

  /**
   * {@inheritdoc}
   *
   * @todo Offer functionality to bulk delete quiz content entities.
   */
  public function validate($module) {
    $reasons = [];
    if ($module == 'quizzes') {
      try {
        $paragraphs = $this->entityTypeManager
          ->getStorage('paragraph')
          ->loadByProperties(['bundle' => 'quiz']);
        if (count($paragraphs) > 0) {
          $reasons[] = $this->t('There is content for the paragraph type: Quiz.');
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        $reasons[] = $this->t('Unable to get quiz paragraph storage.');
      }
    }

    return $reasons;
  }

}
