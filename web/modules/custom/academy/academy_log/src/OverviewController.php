<?php

namespace Drupal\academy_log;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\courses\Entity\Course;
use Drupal\progress\ProgressManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overview for academy log.
 */
class OverviewController implements ContainerInjectionInterface {

  /**
   * Construct overview controller with services.
   */
  public function __construct(
    protected DateFormatter $dateFormatter,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ProgressManager $progressManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('progress.manager'),
    );
  }

  /**
   * Simple overview of academy participants.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function overview(): array {

    // Initialize.
    $page = [];
    $accounts = $this->getCreativeAccounts();
    $courses = $this->getAllCourses();
    $total_courses = !empty($courses) ? count($courses) : 1;

    // Gather progress info for each account.
    foreach ($accounts as $account) {

      // Initialize sheet for this account.
      $sheet = [];

      // Get base information for this account.
      $sheet['name'] = $account->get('field_name')->value;
      $sheet['mail'] = $account->getEmail();
      $overall_progression = $this->calculateProgressionCourses($account);

      // Initialize courses for sheet.
      $sheet['courses'] = [];

      foreach ($courses as $course) {

        // Initialize slip.
        $slip = [];

        // Get progress for course and account.
        $progress = $this->progressManager->loadProgress($course, $account);

        // If there is no progress for the first progress, break and skip this
        // account (see below).
        if (!isset($progress) && $course->getMachineName() === 'intro') {
          break;
        }

        // Base information and progress for this course.
        $slip['title'] = $course->getTitle();
        $course_progression = isset($progress) ? $this->calculateProgressionLectures($course, $account) : 0;
        $slip['progression'] = $course_progression;

        // Timestamps for this course and account.
        $slip['enrolled'] = isset($progress) ? $this->dateFormatter->format($progress->getEnrollmentTime(), 'short') : NULL;
        $accessed = $progress?->getAccessTime();
        $slip['accessed'] = isset($accessed) ? $this->dateFormatter->format($accessed, 'short') : NULL;
        $completed = $progress?->getCompletedTime();
        $slip['completed'] = isset($completed) && $completed !== 0 ? $this->dateFormatter->format($completed, 'short') : NULL;
        if (!isset($accessed)) {
          break;
        }
        $sheet['courses'][$course->id()] = $slip;
        if (isset($completed) && $completed === 0) {
          $overall_progression += $course_progression / $total_courses;
        }
        if (!isset($completed) || $completed === 0) {
          break;
        }
      }

      // If a user never clicked on any courses - do not list.
      if (empty($sheet['courses']) || $overall_progression === 0) {
        continue;
      }

      // Add progression and sheet to page.
      $sheet['progression'] = $overall_progression;
      $page['participants'][$account->id()] = $sheet;
    }

    // Sort participants by progression.
    if (!empty($page['participants'])) {
      usort($page['participants'], static fn($a, $b) => $b['progression'] <=> $a['progression']);
    }

    return [
      '#theme' => 'overview',
      '#page' => $page,
    ];
  }

  /**
   * Gets creative accounts.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function getCreativeAccounts(): array {
    // Get all creatives, that are active and not associates.
    $associates_ids = [
      1, 14, 50, 130, 134, 136, 616, 621, 1200, 1888, 1889, 5124, 15970,
    ];
    $user_storage = $this->entityTypeManager->getStorage('user');
    $user_ids = $user_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', '1')
      ->condition('roles', 'creative')
      ->condition('uid', $associates_ids, 'NOT IN')
      ->execute();
    /** @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $user_storage->loadMultiple($user_ids);
    return $accounts;
  }

  /**
   * Gets all courses.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function getAllCourses(): array {
    // Get all creatives, that are active and not associates.
    $course_storage = $this->entityTypeManager->getStorage('course');
    $course_ids = $course_storage->getQuery()
      ->accessCheck()
      ->condition('status', '1')
      ->sort('weight')
      ->execute();
    /** @var \Drupal\courses\Entity\Course[] $courses */
    $courses = $course_storage->loadMultiple($course_ids);
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
    $completed = count(array_filter($lectures, static fn($l) => $l->completed));
    return (int) ceil($completed / $total * 100);
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
    return (int) ceil($completed / $total * 100);
  }

}
