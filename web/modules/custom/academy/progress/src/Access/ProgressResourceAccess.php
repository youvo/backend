<?php

namespace Drupal\progress\Access;

use Drupal\academy\AcademicFormatInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\courses\Entity\Course;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\ProgressManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access handler for progress rest resources.
 */
class ProgressResourceAccess implements ContainerInjectionInterface {

  /**
   * Constructs a ProgressResourceAccess object.
   */
  public function __construct(protected ProgressManager $progressManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('progress.manager'));
  }

  /**
   * Checks access for progress REST resources.
   */
  public function accessProgress(AccountInterface $account, Route $route, ?AcademicFormatInterface $entity = NULL): AccessResultInterface {

    if (!$entity) {
      return AccessResult::neutral();
    }

    $methods = $route->getMethods();
    $permission = 'restful ' . strtolower(reset($methods)) . ' ' .
      str_replace('.', ':', $route->getDefault('_rest_resource_config'));

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
   *
   * Access is granted if the creative has permission to use this resource,
   * the course and lecture are enabled and unlocked.
   */
  protected function accessLectureProgress(AccountInterface $account, Lecture $lecture, string $permission): AccessResultInterface {
    /** @var \Drupal\courses\Entity\Course $course */
    $course = $lecture->getParentEntity();
    return AccessResult::allowedIf(
      $account->hasPermission($permission) &&
      $course->isPublished() &&
      $lecture->isPublished() &&
      $this->progressManager->isUnlocked($course, $account) &&
      $this->progressManager->isUnlocked($lecture, $account)
    )->cachePerUser();
  }

  /**
   * Checks access for course progress REST resources.
   */
  protected function accessCourseProgress(AccountInterface $account, Course $course, string $permission): AccessResultInterface {
    return AccessResult::allowedIf(
      $course->isPublished() &&
      $account->hasPermission($permission) &&
      $this->progressManager->isUnlocked($course, $account)
    )->cachePerUser();
  }

}
