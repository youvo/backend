<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\projects\Entity\Project;
use Drupal\stats\Stats\FeedbackStats;
use Drupal\stats\StatsPeriod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the stats of feedback.
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
   * Averages the scales and the recommend share per bundle.
   */
  public function testFeedbackAverages(): void {

    $this->setUpScenario();
    [$a, $b] = array_map(fn($id) => Project::load($id), array_slice($this->projectIds, 0, 2));

    $this->createFeedback('creative', $a, '2001-03-05', ['field_satisfied_scale' => 4, 'field_recommend_boolean' => 1]);
    $this->createFeedback('creative', $b, '2001-03-10', ['field_satisfied_scale' => 2, 'field_recommend_boolean' => 0]);
    $this->createFeedback('organization', $a, '2001-03-06', ['field_satisfied_scale' => 5]);
    // Outside the period.
    $this->createFeedback('creative', $a, '2002-03-05', ['field_satisfied_scale' => 1]);

    $feedback = $this->feedbackStats->feedback($this->period);

    $this->assertSame(2, $feedback['creative']['count']);
    $this->assertSame(3.0, $feedback['creative']['scales']['field_satisfied_scale']);
    $this->assertSame(0.5, $feedback['creative']['recommend']);
    $this->assertNull($feedback['creative']['scales']['field_service_scale']);
    $this->assertSame(1, $feedback['organization']['count']);
    $this->assertSame(5.0, $feedback['organization']['scales']['field_satisfied_scale']);
    $this->assertNull($feedback['organization']['recommend']);
  }

}
