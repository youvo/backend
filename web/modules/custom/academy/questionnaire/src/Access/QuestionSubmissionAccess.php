<?php

namespace Drupal\questionnaire\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\questionnaire\Entity\Question;
use Symfony\Component\Routing\Route;

/**
 * Access handler for questionnaire rest resources.
 */
class QuestionSubmissionAccess {

  /**
   * Checks access for question submission.
   */
  public function accessQuestionSubmission(AccountInterface $account, Route $route, ?Question $question = NULL): AccessResultInterface {

    if ($question === NULL) {
      return AccessResult::neutral();
    }

    // Gather properties.
    $methods = $route->getMethods();
    $rest_resource = str_replace('.', ':', $route->getDefault('_rest_resource_config'));
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $question->getParentEntity();
    /** @var \Drupal\lectures\Entity\Lecture $lecture */
    $lecture = $paragraph->getParentEntity();
    /** @var \Drupal\courses\Entity\Course $course */
    $course = $lecture->getParentEntity();

    // Give access to editors.
    if ($account->hasPermission('manage courses')) {
      return AccessResult::allowedIf(
        $account->hasPermission('restful ' . strtolower(reset($methods)) . ' ' . $rest_resource)
      );
    }

    // Return access result.
    return AccessResult::allowedIf(
      $course->isPublished() &&
      $lecture->isPublished() &&
      $account->hasPermission('restful ' . strtolower(reset($methods)) . ' ' . $rest_resource)
    );
  }

}
