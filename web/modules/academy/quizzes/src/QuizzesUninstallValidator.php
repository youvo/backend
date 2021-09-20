<?php

namespace Drupal\quizzes;

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
    if ($module == 'quizzes' && count($this->entityTypeManager->getStorage('paragraph')->loadByProperties(['bundle' => 'quiz'])) > 0) {
      $reasons[] = $this->t('There is content for the paragraph type: Quiz.');
    }
    return $reasons;
  }

}
