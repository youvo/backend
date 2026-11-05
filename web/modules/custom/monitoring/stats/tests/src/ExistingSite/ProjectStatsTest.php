<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\stats\Stats\ProjectStats;
use Drupal\stats\StatsPeriod;
use Drupal\stats\StatsScope;
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
   * Builds the period and scope of a year and a manager.
   *
   * @return array{0: \Drupal\stats\StatsPeriod, 1: \Drupal\stats\StatsScope}
   *   The period and the scope.
   */
  protected function periodAndScope(string $year, int|string $manager): array {
    $query = ['period' => 'year', 'year' => $year, 'manager' => (string) $manager];
    $timezone = $this->timezone();
    return [
      StatsPeriod::fromQuery($query, $timezone, new \DateTimeImmutable('now', $timezone)),
      StatsScope::fromQuery($query, $this->container->get('database')),
    ];
  }

  /**
   * Tests the flow per transition and the cohort of the 2001 scenario.
   */
  public function testFlowAndCohort(): void {

    [$period, $scope] = $this->periodAndScope('2001', $this->setUpScenario()->id());

    $flow = $this->projectStats->flow($period, $scope);
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
    $this->assertCount(7, $this->projectStats->publishRows($period, $scope));

    $cohort = $this->projectStats->cohort($period, $scope);
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
    [$period, $scope] = $this->periodAndScope('2001', $this->setUpScenario()->id());
    $this->assertSame([
      'submit_publish' => 4.0,
      'publish_mediate' => 9.0,
      'mediate_complete' => 28.0,
    ], $this->projectStats->stageMedians($period, $scope));
  }

  /**
   * Tests the running totals of mediated and completed projects.
   */
  public function testCumulative(): void {

    [$period, $scope] = $this->periodAndScope('2001', $this->setUpScenario()->id());

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
    ], $this->projectStats->cumulative($period, $scope));
  }

  /**
   * Creates a manager with a reset and an archived mediated project in 2001.
   */
  protected function setUpMediatedTwice(): Creative {

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

    return $manager;
  }

  /**
   * Tests that reset or archived mediated projects keep the rate at 100 %.
   */
  public function testRateNeverExceedsHundred(): void {
    [$period, $scope] = $this->periodAndScope('2001', $this->setUpMediatedTwice()->id());
    $cohort = $this->projectStats->cohort($period, $scope);
    $this->assertSame(2, $cohort['published']);
    $this->assertSame(2, $cohort['mediated']);
    $this->assertSame(0, $cohort['excluded']);
    $this->assertSame(1.0, $cohort['mediation_rate']);
  }

  /**
   * Tests a period without data: zero counts and no rates or medians.
   */
  public function testEmptyPeriod(): void {

    [$period, $scope] = $this->periodAndScope('2002', $this->setUpMediatedTwice()->id());
    $this->assertNotEmpty($scope->organizationIds());

    $flow = $this->projectStats->flow($period, $scope);
    $this->assertCount(6, $flow);
    foreach ($flow as $buckets) {
      $this->assertSame(array_fill_keys(array_keys($period->buckets()), 0), $buckets);
    }

    $cohort = $this->projectStats->cohort($period, $scope);
    $this->assertSame(0, $cohort['published']);
    $this->assertNull($cohort['mediation_rate']);
    $this->assertNull($cohort['completion_rate']);

    $this->assertSame([
      'submit_publish' => NULL,
      'publish_mediate' => NULL,
      'mediate_complete' => NULL,
    ], $this->projectStats->stageMedians($period, $scope));

    // A manager without organizations yields zeros as well.
    [$period, $scope] = $this->periodAndScope('2002', $this->createManager()->id());
    $this->assertSame([], $scope->organizationIds());
    $this->assertSame(0, $this->projectStats->cohort($period, $scope)['published']);
  }

  /**
   * Tests that the scope only filters by users with the manager role.
   */
  public function testScopeIgnoresNonManager(): void {

    $database = $this->container->get('database');
    $creative = $this->createCreative();
    $this->assertNull(StatsScope::fromQuery(['manager' => (string) $creative->id()], $database)->organizationIds());
    $this->assertNull(StatsScope::fromQuery(['manager' => 'abc'], $database)->organizationIds());

    $manager = $this->createManager();
    $organization = $this->createOrganization($manager, '2001-01-01');
    $scope = StatsScope::fromQuery(['manager' => (string) $manager->id()], $database);
    $this->assertSame((int) $manager->id(), $scope->managerId());
    $this->assertSame([(int) $organization->id()], $scope->organizationIds());
  }

}
