<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\stats\Stats\ProjectStats;
use Drupal\stats\StatsPeriod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project stats computed from the lifecycle history.
 */
#[Group('stats')]
class ProjectStatsTest extends StatsTestBase {

  /**
   * The project stats service.
   */
  protected ProjectStats $projectStats;

  /**
   * Gets the project stats service.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->projectStats = $this->container->get('stats.project_stats');
  }

  /**
   * Builds the period of a year.
   */
  protected function year(string $year): StatsPeriod {
    $timezone = $this->timezone();
    return StatsPeriod::fromQuery(['period' => 'year', 'year' => $year], $timezone, new \DateTimeImmutable('now', $timezone));
  }

  /**
   * Tests the flow per transition and the cohort of the 2018 scenario.
   */
  public function testFlowAndCohort(): void {

    $this->setUpScenario();
    $period = $this->year('2018');

    $flow = $this->projectStats->flow($period);
    $totals = array_map('array_sum', $flow);
    $this->assertSame([
      'submit' => 7,
      'publish' => 7,
      'mediate' => 4,
      'complete' => 1,
      'reset' => 1,
      'decline' => 1,
    ], $totals);
    $expected = array_fill_keys(array_keys($period->buckets()), 0);
    $expected['2018-01'] = 1;
    $expected['2018-02'] = 1;
    $expected['2018-03'] = 2;
    $expected['2018-04'] = 1;
    $expected['2018-05'] = 1;
    $expected['2018-07'] = 1;
    $this->assertSame($expected, $flow['publish']);

    $cohort = $this->projectStats->cohort($period);
    $this->assertSame(6, $cohort['published']);
    $this->assertSame(3, $cohort['mediated']);
    $this->assertSame(1, $cohort['completed']);
    $this->assertSame(0.75, $cohort['mediation_rate']);
  }

  /**
   * Tests the median days between the stages of the 2018 scenario.
   */
  public function testStageMedians(): void {
    $this->setUpScenario();
    $period = $this->year('2018');
    $this->assertSame([
      'submit_publish' => 4.0,
      'publish_application' => NULL,
      'publish_mediate' => 9.0,
      'mediate_complete' => 28.0,
    ], $this->projectStats->stageMedians($period));
  }

  /**
   * Tests the projects per state at each month end of 2018.
   */
  public function testStatesAtBucketEnds(): void {

    $this->setUpScenario();
    $period = $this->year('2018');
    $states = $this->projectStats->statesAtBucketEnds($period, time());

    // The month ends of the scenario, archived D and H included: A open in
    // January, F ongoing since January, A and B ongoing in February, A
    // completed and C and D open in March, E ongoing in April and open again
    // in May, H open in July.
    $months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
    $series = fn(array $values) => array_combine(array_map(fn($month) => '2018-' . $month, $months), $values);
    $expected = [
      'open' => $series([1, 0, 2, 2, 3, 3, 4, 4, 4, 4, 4, 4]),
      'ongoing' => $series([1, 3, 2, 3, 2, 2, 2, 2, 2, 2, 2, 2]),
      'completed' => $series([0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1]),
      'mediated' => $series([1, 3, 3, 4, 3, 3, 3, 3, 3, 3, 3, 3]),
    ];
    $this->assertSame($expected, $states);
  }

  /**
   * Tests that the buckets of a running period from now on count now.
   */
  public function testStatesInRunningPeriod(): void {

    $this->setUpScenario();
    $timezone = $this->timezone();
    $now = new \DateTimeImmutable('now', $timezone);
    $period = StatsPeriod::fromQuery(['period' => 'year', 'year' => $now->format('Y')], $timezone, $now);
    $states = $this->projectStats->statesAtBucketEnds($period, $now->getTimestamp());

    // The current month counts the current states, the later months none.
    $calculator = $this->container->get('stats.calculator');
    $current = [
      'open' => $calculator->countOpenProjects(),
      'ongoing' => $calculator->countOngoingProjects(),
      'completed' => $calculator->countCompletedProjects(),
      'mediated' => $calculator->countMediatedProjects(),
    ];
    foreach ($states as $state => $values) {
      foreach ($values as $bucket => $value) {
        if ($bucket === $now->format('Y-m')) {
          $this->assertSame($current[$state], $value, $state . ' ' . $bucket);
        }
        if ($bucket > $now->format('Y-m')) {
          $this->assertNull($value, $state . ' ' . $bucket);
        }
      }
    }
  }

  /**
   * Creates a reset and an archived mediated project in 2018.
   */
  protected function setUpMediatedTwice(): void {

    $manager = $this->createManager();
    $organization = $this->createOrganization($manager, '2018-01-01');
    $x = $this->createProjectWithHistory($organization, [
      ['submit', '2018-02-01'],
      ['publish', '2018-02-05'],
      ['mediate', '2018-03-01'],
      ['reset', '2018-04-01'],
      ['submit', '2018-04-05'],
      ['publish', '2018-04-10'],
    ]);
    $this->archive($x, 'external', '2018-05-01');
    $this->createProjectWithHistory($organization, [
      ['submit', '2018-02-01'],
      ['publish', '2018-02-05'],
      ['mediate', '2018-03-01'],
    ]);
  }

  /**
   * Tests that reset or archived mediated projects keep the rate at 100 %.
   */
  public function testRateNeverExceedsHundred(): void {
    $this->setUpMediatedTwice();
    $cohort = $this->projectStats->cohort($this->year('2018'));
    $this->assertSame(2, $cohort['published']);
    $this->assertSame(2, $cohort['mediated']);
    $this->assertSame(1.0, $cohort['mediation_rate']);
  }

  /**
   * Tests a period without data: zero counts and no rates or medians.
   */
  public function testEmptyPeriod(): void {

    $this->setUpMediatedTwice();
    $period = $this->year('2019');

    $flow = $this->projectStats->flow($period);
    $this->assertCount(6, $flow);
    foreach ($flow as $buckets) {
      $this->assertSame(array_fill_keys(array_keys($period->buckets()), 0), $buckets);
    }

    $cohort = $this->projectStats->cohort($period);
    $this->assertSame(0, $cohort['published']);
    $this->assertNull($cohort['mediation_rate']);

    $this->assertSame([
      'submit_publish' => NULL,
      'publish_application' => NULL,
      'publish_mediate' => NULL,
      'mediate_complete' => NULL,
    ], $this->projectStats->stageMedians($period));
  }

}
