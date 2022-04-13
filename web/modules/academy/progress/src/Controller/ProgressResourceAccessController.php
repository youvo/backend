<?php

namespace Drupal\progress\Controller;

use Drupal\academy\AcademicFormatInterface;
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
class ProgressResourceAccessController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The progress manager service.
   *
   * @var \Drupal\progress\ProgressManager
   */
  private $progressManager;

  /**
   * Constructs a ProgressResourceAccessController object.
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
   * @param \Drupal\academy\AcademicFormatInterface|null $entity
   *   The lecture entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   */
  public function accessProgress(AccountInterface $account, Route $route, AcademicFormatInterface $entity = NULL) {

    // Return, if entity is empty.
    if (!$entity) {
      return AccessResult::neutral();
    }

    // Gather properties.
    $methods = $route->getMethods();
    $permission = 'restful ' . strtolower(reset($methods)) . ' ' .
      strtr($route->getDefault('_rest_resource_config'), '.', ':');

    // Give access to editors.
    if ($account->hasPermission('manage courses')) {
      return AccessResult::allowedIfHasPermission($account, $permission);
    }

    if ($entity instanceof Lecture) {
      return $this->accessLectureProgress($account, $entity, $permission);
    }

    if ($entity instanceof Course) {
      return $this->accessCourseProgress($account, $entity, $permission);
    }

    return AccessResult::neutral();
  }

  /**
   * Checks access for lecture progress REST resources.
   */
  protected function accessLectureProgress(AccountInterface $account, Lecture $lecture, string $permission) {

    // Access is granted if the creative has permission to use this resource,
    // the course and lecture are enabled and unlocked.
    return AccessResult::allowedIf(
      $account->hasPermission($permission) &&
      $lecture->getParentEntity()->isEnabled() &&
      $lecture->isEnabled() &&
      $this->progressManager->isUnlocked($lecture->getParentEntity(), $account) &&
      $this->progressManager->isUnlocked($lecture, $account)
    )->cachePerUser();
  }

  /**
   * Checks access for course progress REST resources.
   */
  protected function accessCourseProgress(AccountInterface $account, Course $course, string $permission) {
    return AccessResult::allowedIf(
      $course->isPublished() &&
      $account->hasPermission($permission) &&
      $this->progressManager->isUnlocked($course, $account)
    )->cachePerUser();
  }

}
