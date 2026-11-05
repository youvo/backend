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
   * Tests the flow per transition and the cohort of the 2001 scenario.
   */
  public function testFlowAndCohort(): void {

    $this->setUpScenario();
    $period = $this->year('2001');

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
    $expected['2001-01'] = 1;
    $expected['2001-02'] = 1;
    $expected['2001-03'] = 2;
    $expected['2001-04'] = 1;
    $expected['2001-05'] = 1;
    $expected['2001-07'] = 1;
    $this->assertSame($expected, $flow['publish']);
    $this->assertCount(7, $this->projectStats->publishRows($period));

    $cohort = $this->projectStats->cohort($period);
    $this->assertSame(6, $cohort['published']);
    $this->assertCount(6, $cohort['project_ids']);
    $this->assertSame(3, $cohort['mediated']);
    $this->assertSame(1, $cohort['completed']);
    $this->assertSame(2, $cohort['excluded']);
    $this->assertSame(0.75, $cohort['mediation_rate']);
    $this->assertEqualsWithDelta(0.333, $cohort['completion_rate'], 0.001);
  }

  /**
   * Tests the median days between the stages of the 2001 scenario.
   */
  public function testStageMedians(): void {
    $this->setUpScenario();
    $period = $this->year('2001');
    $this->assertSame([
      'submit_publish' => 4.0,
      'publish_mediate' => 9.0,
      'mediate_complete' => 28.0,
    ], $this->projectStats->stageMedians($period));
  }

  /**
   * Tests the running totals of mediated and completed projects.
   */
  public function testCumulative(): void {

    $this->setUpScenario();
    $period = $this->year('2001');

    $mediated = array_fill_keys(array_keys($period->buckets()), 4);
    $mediated['2001-01'] = 1;
    $mediated['2001-02'] = 3;
    $mediated['2001-03'] = 3;
    $completed = array_fill_keys(array_keys($period->buckets()), 1);
    $completed['2001-01'] = 0;
    $completed['2001-02'] = 0;
    $this->assertSame([
      'mediated' => $mediated,
      'completed' => $completed,
    ], $this->projectStats->cumulative($period));
  }

  /**
   * Creates a reset and an archived mediated project in 2001.
   */
  protected function setUpMediatedTwice(): void {

    $manager = $this->createManager();
    $organization = $this->createOrganization($manager, '2001-01-01');
    $x = $this->createProjectWithHistory($organization, [
      ['submit', '2001-02-01'],
      ['publish', '2001-02-05'],
      ['mediate', '2001-03-01'],
      ['reset', '2001-04-01'],
      ['submit', '2001-04-05'],
      ['publish', '2001-04-10'],
    ]);
    $this->archive($x, 'external', '2001-05-01');
    $this->createProjectWithHistory($organization, [
      ['submit', '2001-02-01'],
      ['publish', '2001-02-05'],
      ['mediate', '2001-03-01'],
    ]);
  }

  /**
   * Tests that reset or archived mediated projects keep the rate at 100 %.
   */
  public function testRateNeverExceedsHundred(): void {
    $this->setUpMediatedTwice();
    $cohort = $this->projectStats->cohort($this->year('2001'));
    $this->assertSame(2, $cohort['published']);
    $this->assertSame(2, $cohort['mediated']);
    $this->assertSame(0, $cohort['excluded']);
    $this->assertSame(1.0, $cohort['mediation_rate']);
  }

  /**
   * Tests a period without data: zero counts and no rates or medians.
   */
  public function testEmptyPeriod(): void {

    $this->setUpMediatedTwice();
    $period = $this->year('2002');

    $flow = $this->projectStats->flow($period);
    $this->assertCount(6, $flow);
    foreach ($flow as $buckets) {
      $this->assertSame(array_fill_keys(array_keys($period->buckets()), 0), $buckets);
    }

    $cohort = $this->projectStats->cohort($period);
    $this->assertSame(0, $cohort['published']);
    $this->assertNull($cohort['mediation_rate']);
    $this->assertNull($cohort['completion_rate']);

    $this->assertSame([
      'submit_publish' => NULL,
      'publish_mediate' => NULL,
      'mediate_complete' => NULL,
    ], $this->projectStats->stageMedians($period));
  }

}
