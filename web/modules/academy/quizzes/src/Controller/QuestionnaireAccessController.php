<?php

namespace Drupal\quizzes\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quizzes\Entity\Quiz;
use Symfony\Component\Routing\Route;

/**
 * Access controller for transition forms.
 */
class QuestionnaireAccessController extends ControllerBase {

  /**
   * Checks access for questionnaire submission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Symfony\Component\Routing\Route $route
   *   The requested route.
   * @param \Drupal\paragraphs\Entity\Paragraph|null $questionnaire
   *   The questionnaire entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessQuestionnaire(AccountInterface $account, Route $route, Paragraph $questionnaire = NULL) {
    if ($questionnaire instanceof Quiz) {
      $methods = $route->getMethods();
      return AccessResult::allowedIf($account->hasPermission('restful ' . strtolower(reset($methods)) . ' questionnaire:submission'));
    }
    return AccessResult::forbidden();
  }

}
