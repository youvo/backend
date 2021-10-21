<?php

namespace Drupal\progress\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\lectures\Entity\Lecture;
use Symfony\Component\Routing\Route;

/**
 * Access controller for lecture rest resources.
 */
class LectureAccessController extends ControllerBase {

  /**
   * Checks access for lecture completion.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Symfony\Component\Routing\Route $route
   *   The requested route.
   * @param \Drupal\lectures\Entity\Lecture|null $lecture
   *   The lecture entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessLecture(AccountInterface $account, Route $route, Lecture $lecture = NULL) {
    $methods = $route->getMethods();
    $rest_resource = strtr($route->getDefault('_rest_resource_config'), '.', ':');
    $course = $lecture->getParentEntity();
    return AccessResult::allowedIf(
      $course->isEnabled() &&
      $lecture->isEnabled() &&
      $account->hasPermission('restful ' . strtolower(reset($methods)) . ' ' . $rest_resource)
    );
  }

}
