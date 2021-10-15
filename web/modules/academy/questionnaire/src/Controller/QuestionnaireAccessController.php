<?php

namespace Drupal\questionnaire\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\questionnaire\Entity\Questionnaire;
use Symfony\Component\Routing\Route;

/**
 * Access controller for questionnaire rest resources.
 */
class QuestionnaireAccessController extends ControllerBase {

  /**
   * Checks access for questionnaire submission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Symfony\Component\Routing\Route $route
   *   The requested route.
   * @param \Drupal\paragraphs\Entity\Paragraph|null $paragraph
   *   The questionnaire entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessQuestionnaire(AccountInterface $account, Route $route, Paragraph $paragraph = NULL) {
    if ($paragraph instanceof Questionnaire) {
      $methods = $route->getMethods();
      $rest_resource = strtr($route->getDefault('_rest_resource_config'), '.', ':');
      $lecture = $paragraph->getParentEntity();
      $course = $lecture->getParentEntity();
      return AccessResult::allowedIf(
        $course->isEnabled() &&
        $lecture->isEnabled() &&
        $account->hasPermission('restful ' . strtolower(reset($methods)) . ' ' . $rest_resource)
      );
    }
    return AccessResult::forbidden();
  }

}
