<?php

namespace Drupal\academy_log;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Session\AccountInterface;
use Drupal\courses\Entity\Course;
use Drupal\progress\ProgressManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overview for academy log.
 */
class OverviewController extends ControllerBase {

  /**
   * The progress manager service.
   *
   * @var \Drupal\progress\ProgressManager
   */
  private ProgressManager $progressManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  private DateFormatter $dateFormatter;

  /**
   * Construct overview controller with services.
   *
   * @param \Drupal\progress\ProgressManager $progress_manager
   *   The progress manager service.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   */
  public function __construct(ProgressManager $progress_manager, DateFormatter $date_formatter) {
    $this->progressManager = $progress_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('progress.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * Simple overview of academy participants.
   */
  public function overview() {

    // Initialize.
    $page = [];
    $accounts = $this->getCreativeAccounts();
    $courses = $this->getAllCourses();
    $total_courses = !empty($courses) ? count($courses) : 1;

    // Gather progress info for each account.
    foreach ($accounts as $account) {
      $sheet = [];
      $sheet['name'] = $account->get('fullname')->value;
      $overall_progression = $this->calculateProgressionCourses($account);
      $sheet['courses'] = [];
      foreach ($courses as $course) {
        $slip = [];
        $progress = $this->progressManager->loadProgress($course, $account);
        if ($course->id() == 1 && !isset($progress)) {
          break;
        }
        $slip['title'] = $course->getTitle();
        $course_progression = isset($progress) ? $this->calculateProgressionLectures($course, $account) : 0;
        $slip['progression'] = $course_progression;
        $slip['enrolled'] = isset($progress) ? $this->dateFormatter->format($progress->getEnrollmentTime(), 'short') : NULL;
        $slip['accessed'] = isset($progress) ? $this->dateFormatter->format($progress->getAccessTime(), 'short') : NULL;
        $completed = $progress?->getCompletedTime();
        $slip['completed'] = isset($completed) && $completed != 0 ? $this->dateFormatter->format($completed, 'short') : NULL;
        $sheet['courses'][$course->id()] = $slip;
        if (isset($completed) && $completed == 0) {
          $overall_progression += $course_progression / $total_courses;
        }
        if (!isset($completed) || $completed == 0) {
          break;
        }
      }
      // If a user never clicked on any courses - do not list.
      if (empty($sheet['courses'])) {
        continue;
      }
      $sheet['progression'] = $overall_progression;
      $page['participants'][$account->id()] = $sheet;
    }

    // Sort participants by progression.
    usort($page['participants'],
      fn($a, $b) => $b['progression'] <=> $a['progression']);

    return [
      '#theme' => 'overview',
      '#page' => $page,
    ];
  }

  /**
   * Gets creative accounts.
   */
  private function getCreativeAccounts() {
    // Get all creatives, that are active and not associates.
    $associates_ids = [1, 14, 130, 136, 616, 621, 1200, 1888, 1889, 5124, 15970];
    $storage = $this->entityTypeManager()->getStorage('user');
    $uids = $storage->getQuery()
      ->condition('status', '1')
      ->condition('roles', 'creative')
      ->condition('uid', $associates_ids, 'NOT IN')
      ->execute();
    /** @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $storage->loadMultiple($uids);
    return $accounts;
  }

  /**
   * Gets creative accounts.
   */
  private function getAllCourses() {
    // Get all creatives, that are active and not associates.
    $storage = $this->entityTypeManager()->getStorage('course');
    $cids = $storage->getQuery()
      ->condition('status', '1')
      ->sort('weight')
      ->execute();
    /** @var \Drupal\courses\Entity\Course[] $courses */
    $courses = $storage->loadMultiple($cids);
    return $courses;
  }

  /**
   * Returns progress in percent for course.
   */
  private function calculateProgressionLectures(Course $course, AccountInterface $account): int {

    // Get lectures by completed for this course and account.
    $lectures = $this->progressManager->getReferencedLecturesByCompleted($course, $account);

    // If there are no lectures, there is no progress.
    if (empty($lectures)) {
      return 0;
    }

    // Calculate percentage.
    $total = count($lectures);
    $completed = count(array_filter($lectures, fn($l) => $l->completed));
    return ceil($completed / $total * 100);
  }

  /**
   * Returns progress in percent for curriculum.
   */
  private function calculateProgressionCourses(AccountInterface $account): int {

    // Get courses by completed for this account.
    $courses = $this->progressManager->getCoursesByCompleted($account);

    // If there are no courses, there is no progress.
    if (empty($courses)) {
      return 0;
    }

    // Calculate percentage.
    $total = count($courses);
    $completed = count(array_filter($courses, fn($c) => $c->completed));
    return ceil($completed / $total * 100);
  }

}
