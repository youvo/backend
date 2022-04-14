<?php

namespace Drupal\questionnaire\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\questionnaire\Entity\Question;
use Symfony\Component\Routing\Route;

/**
 * Access controller for questionnaire rest resources.
 */
class QuestionSubmissionAccessController extends ControllerBase {

  /**
   * Checks access for question submission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Symfony\Component\Routing\Route $route
   *   The requested route.
   * @param \Drupal\questionnaire\Entity\Question|null $question
   *   The questionnaire entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessQuestionSubmission(AccountInterface $account, Route $route, Question $question = NULL) {

    // Gather properties.
    $methods = $route->getMethods();
    $rest_resource = strtr($route->getDefault('_rest_resource_config'), '.', ':');
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
