<?php

namespace Drupal\progress\Controller;

use Drupal\academy\Entity\AcademicFormat;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\courses\Entity\Course;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\ProgressManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access controller for lecture rest resources.
 */
class ProgressAccessController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The progress manager service.
   *
   * @var \Drupal\progress\ProgressManager
   */
  private $progressManager;

  /**
   * Constructs a Drupal\progress\Controller\ProgressAccessController object.
   *
   * @param \Drupal\progress\ProgressManager $progress_manager
   *   The progress manager service.
   */
  public function __construct(ProgressManager $progress_manager) {
    $this->progressManager = $progress_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('progress.manager')
    );
  }

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
      return AccessResult::allowedIfHasPermission($account, 'restful ' . $method . ' ' . $rest_resource);
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

    // Check whether creative is enrolled in the course.
    // And if creative is enrolled or wants to enroll in the lecture.
    $enrollment = FALSE;
    if ($this->progressManager->isEnrolled($lecture->getParentEntity(), $account)) {
      if ($this->progressManager->isEnrolled($lecture, $account) ||
        ($method == 'post' && $this->progressManager->getUnlockedStatus($lecture, $account))
      ) {
        $enrollment = TRUE;
      }
    }

    // Access is granted if the creative has permission to use this resource,
    // the course and lecture are enabled. Additionally, the enrollment status
    // is checked.
    return AccessResult::allowedIf(
      $account->hasPermission('restful ' . $method . ' ' . $rest_resource) &&
      $lecture->getParentEntity()->isEnabled() &&
      $lecture->isEnabled() &&
      $enrollment
    )->cachePerUser();
  }

  /**
   * Checks access for course progress REST resources.
   */
  protected function accessCourseProgress(AccountInterface $account, Course $course, string $method, string $rest_resource) {
    return AccessResult::allowedIf(
      $this->progressManager->getUnlockedStatus($course, $account) &&
      $course->isEnabled() &&
      $account->hasPermission('restful ' . $method . ' ' . $rest_resource)
    )->cachePerUser();
  }

}
