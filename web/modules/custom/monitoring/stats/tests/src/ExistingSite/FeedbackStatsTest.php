<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\feedback\Entity\Feedback;
use Drupal\progress\Entity\CourseProgress;
use Drupal\projects\Entity\Project;
use Drupal\stats\Stats\FeedbackStats;
use Drupal\stats\StatsPeriod;
use Drupal\stats\StatsScope;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the stats of feedback and academy.
 */
#[Group('stats')]
class FeedbackStatsTest extends StatsTestBase {

  /**
   * The feedback stats service.
   */
  protected FeedbackStats $feedbackStats;

  /**
   * The year 2001.
   */
  protected StatsPeriod $period;

  /**
   * Gets the services and the period.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->feedbackStats = $this->container->get('stats.feedback_stats');
    $timezone = $this->timezone();
    $this->period = StatsPeriod::fromQuery(['period' => 'year', 'year' => '2001'], $timezone, new \DateTimeImmutable('now', $timezone));
  }

  /**
   * Creates a completed feedback of a bundle on a Y-m-d date.
   *
   * @param string $bundle
   *   The feedback bundle.
   * @param \Drupal\projects\Entity\Project $project
   *   The project.
   * @param string $date
   *   The completion date.
   * @param array<string, int> $values
   *   The extra field values.
   */
  protected function createFeedback(string $bundle, Project $project, string $date, array $values): void {
    $feedback = Feedback::create($values + [
      'bundle' => $bundle,
      'project' => $project->id(),
      'author' => $bundle === 'organization' ? $project->getOwnerId() : $this->createCreative()->id(),
      'step' => 0,
      'completed' => $this->noon($date),
    ]);
    $feedback->save();
    $this->markEntityForCleanup($feedback);
  }

  /**
   * Averages the scales and the recommend share per bundle.
   */
  public function testFeedbackAverages(): void {

    $manager = $this->setUpScenario();
    $scope = StatsScope::fromQuery(['manager' => (string) $manager->id()], $this->container->get('database'));
    [$a, $b] = array_map(fn($id) => Project::load($id), array_slice($this->projectIds, 0, 2));

    $this->createFeedback('creative', $a, '2001-03-05', ['field_satisfied_scale' => 4, 'field_recommend_boolean' => 1]);
    $this->createFeedback('creative', $b, '2001-03-10', ['field_satisfied_scale' => 2, 'field_recommend_boolean' => 0]);
    $this->createFeedback('organization', $a, '2001-03-06', ['field_satisfied_scale' => 5]);
    // Outside the period.
    $this->createFeedback('creative', $a, '2002-03-05', ['field_satisfied_scale' => 1]);

    $feedback = $this->feedbackStats->feedback($this->period, $scope);

    $this->assertSame(2, $feedback['creative']['count']);
    $this->assertSame(3.0, $feedback['creative']['scales']['field_satisfied_scale']);
    $this->assertSame(0.5, $feedback['creative']['recommend']);
    $this->assertNull($feedback['creative']['scales']['field_service_scale']);
    $this->assertSame(1, $feedback['organization']['count']);
    $this->assertSame(5.0, $feedback['organization']['scales']['field_satisfied_scale']);
    $this->assertNull($feedback['organization']['recommend']);
  }

  /**
   * Counts enrollments and completions in the period.
   */
  public function testAcademy(): void {

    $courses = $this->container->get('entity_type.manager')->getStorage('course')->loadMultiple();
    if (!$courses) {
      $this->markTestSkipped('The site has no course to enroll in.');
    }
    $course = reset($courses);

    $before = $this->feedbackStats->academy($this->period);
    foreach ([['2001-02-01', 0], ['2001-03-01', $this->noon('2001-04-01')]] as [$enrolled, $completed]) {
      $progress = CourseProgress::create([
        'uid' => $this->createCreative()->id(),
        'course' => $course->id(),
        'enrolled' => $this->noon($enrolled),
        'accessed' => $this->noon($enrolled),
        'completed' => $completed,
      ]);
      $progress->save();
      $this->markEntityForCleanup($progress);
    }

    $academy = $this->feedbackStats->academy($this->period);
    $this->assertSame($before['enrolled'] + 2, $academy['enrolled']);
    $this->assertSame($before['completed'] + 1, $academy['completed']);
  }

}
