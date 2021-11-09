<?php

namespace Drupal\progress\Controller;

use Drupal\academy\Entity\AcademicFormat;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\courses\Entity\Course;
use Drupal\lectures\Entity\Lecture;
use Symfony\Component\Routing\Route;

/**
 * Access controller for lecture rest resources.
 */
class ProgressAccessController extends ControllerBase {

  /**
   * Checks access for progress REST resources.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Symfony\Component\Routing\Route $route
   *   The requested route.
   * @param \Drupal\academy\Entity\AcademicFormat|null $entity
   *   The lecture entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProgress(AccountInterface $account, Route $route, AcademicFormat $entity = NULL) {

    // Return, if entity is empty.
    if (!$entity) {
      return AccessResult::neutral();
    }

    // Gather properties.
    $methods = $route->getMethods();
    $method = strtolower(reset($methods));
    $rest_resource = strtr($route->getDefault('_rest_resource_config'), '.', ':');

    // Give access to editors.
    if ($account->hasPermission('manage courses')) {
      return AccessResult::allowedIf(
        $account->hasPermission('restful ' . $method . ' ' . $rest_resource)
      );
    }

    if ($entity instanceof Lecture) {
      return $this->accessLectureProgress($account, $entity, $method, $rest_resource);
    }

    if ($entity instanceof Course) {
      return $this->accessCourseProgress($account, $entity, $method, $rest_resource);
    }

    return AccessResult::neutral();
  }

  /**
   * Checks access for lecture progress REST resources.
   */
  protected function accessLectureProgress(AccountInterface $account, Lecture $lecture, string $method, string $rest_resource) {
    return AccessResult::allowedIf(
      $lecture->getParentEntity()->isEnabled() &&
      $lecture->isEnabled() &&
      $account->hasPermission('restful ' . $method . ' ' . $rest_resource)
    );
  }

  /**
   * Checks access for course progress REST resources.
   */
  protected function accessCourseProgress(AccountInterface $account, Course $course, string $method, string $rest_resource) {
    return AccessResult::allowedIf(
      $course->isEnabled() &&
      $account->hasPermission('restful ' . $method . ' ' . $rest_resource)
    );
  }

}
