<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\stats\Stats\ActivityStats;
use Drupal\stats\StatsPeriod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the stats of applications, creatives and organizations.
 */
#[Group('stats')]
class ActivityStatsTest extends StatsTestBase {

  /**
   * The activity stats service.
   */
  protected ActivityStats $activityStats;

  /**
   * The year 2001.
   */
  protected StatsPeriod $period;

  /**
   * Builds the scenario with applications, mediations and a prospect.
   */
  protected function setUp(): void {

    parent::setUp();
    $this->activityStats = $this->container->get('stats.activity_stats');
    $timezone = $this->timezone();
    $this->period = StatsPeriod::fromQuery(['period' => 'year', 'year' => '2001'], $timezone, new \DateTimeImmutable('now', $timezone));

    $this->setUpScenario();
    // The scenario creates the projects A to H in this order.
    [$a, $b, $c, , , $f, $g] = array_map(fn($id) => Project::load($id), $this->projectIds);

    // W and the last creative never logged in.
    $x = $this->createCreativeOn('2001-01-02', TRUE);
    $y = $this->createCreativeOn('2001-01-03', TRUE);
    $w = $this->createCreativeOn('2001-02-01');
    $z = $this->createCreativeOn('2000-12-01', TRUE);
    $this->createCreativeOn('2001-03-01');

    $this->apply($z, $f, '2000-12-29');
    $this->apply($x, $a, '2001-01-16');
    $this->apply($y, $a, '2001-01-18');
    $this->apply($x, $b, '2001-02-11');
    $this->apply($w, $b, '2001-02-12');
    $this->apply($z, $c, '2001-03-08');
    $this->apply($y, $c, '2001-03-09');

    $this->createLog('project_mediate', '2001-02-01', ['project' => $a, 'creatives' => [$x]]);
    $this->createLog('project_mediate', '2001-02-20', ['project' => $b, 'creatives' => [$x]]);
    $this->createLog('project_mediate', '2001-01-05', ['project' => $f, 'creatives' => [$z]]);
    $this->createLog('project_decline', '2001-06-05', ['project' => $g, 'misc' => ['reason' => 'imprecise']]);

    $prospect = $this->createOrganizationUser('prospect');
    $prospect->set('created', $this->noon('2001-05-01'))->save();
  }

  /**
   * Creates a creative registered on a Y-m-d date.
   *
   * @param string $date
   *   The registration date.
   * @param bool $logged_in
   *   Whether the creative has logged in since, on the same day.
   */
  protected function createCreativeOn(string $date, bool $logged_in = FALSE): Creative {
    $creative = $this->createCreative();
    $creative->set('created', $this->noon($date));
    if ($logged_in) {
      $creative->set('access', $this->noon($date) + 3600);
    }
    $creative->save();
    return $creative;
  }

  /**
   * Creates the application log of a creative for a project.
   */
  protected function apply(Creative $creative, ProjectInterface $project, string $date): void {
    $this->createLog('project_apply', $date, ['project' => $project, 'creatives' => [$creative]]);
  }

  /**
   * Gets twelve monthly values from the values of the first months.
   *
   * @param int[] $values
   *   The values from January on.
   * @param int $rest
   *   The value of the remaining months.
   *
   * @return array<string, int>
   *   The values keyed by month of 2001.
   */
  protected function months(array $values, int $rest = 0): array {
    $months = [];
    for ($month = 1; $month <= 12; $month++) {
      $months[sprintf('2001-%02d', $month)] = $values[$month - 1] ?? $rest;
    }
    return $months;
  }

  /**
   * Tests applications, applicants, engagement and returns after rejection.
   */
  public function testApplicationsAndCreatives(): void {

    // The scenario projects get logs from createLog() only.
    $logs = $this->container->get('database')->select('log', 'l')
      ->condition('l.project', $this->projectIds, 'IN')
      ->condition('l.type', ['project_apply', 'project_mediate', 'project_decline'], 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(11, $logs);

    $applications = $this->activityStats->applications($this->period);
    $this->assertSame(6, $applications['total']);
    $this->assertSame($this->months([2, 2, 2]), $applications['per_bucket']);
    $this->assertSame(4, $applications['active']);
    $this->assertSame($this->months([2, 2, 2]), $applications['active_per_bucket']);
    $this->assertSame(3, $applications['first_time']);

    $this->assertSame(2, $this->activityStats->engagedCreatives($this->period));
    // Y, rejected on A, applied to C later; W, rejected on B, did not.
    $this->assertSame(0.5, $this->activityStats->returnAfterRejection($this->period));

    $publish_rows = $this->container->get('stats.project_stats')->publishRows($this->period);
    $this->assertSame(1.0, $this->activityStats->firstApplicationMedian($this->period, $publish_rows));
  }

  /**
   * Tests registrations, the prospect funnel and the reasons.
   */
  public function testOrganizationsAndReasons(): void {

    $registrations = $this->activityStats->registrations($this->period);
    // They count as the calculator: creatives who logged in, X and Y in
    // January after Z in 2000, and organizations without the prospect, O1 in
    // January and O2 in March after O3 in 2000.
    $this->assertSame($this->months([], 3), $registrations['creatives']);
    $this->assertSame($this->months([2, 2], 3), $registrations['organizations']);

    $this->assertSame(['created' => 3, 'managed' => 2, 'published' => 2], $this->activityStats->prospectFunnel($this->period));

    $this->assertSame(['imprecise' => 1], $this->activityStats->reasons($this->period, 'project_decline'));
    $this->assertSame(['external' => 1, 'unattractive' => 1], $this->activityStats->reasons($this->period, 'project_archive'));
  }

  /**
   * Tests the activation and the first application after registration.
   */
  public function testActivation(): void {

    $activation = $this->activityStats->activation($this->period);
    $this->assertSame($this->months([2, 1, 1]), $activation['registered']);
    $this->assertSame($this->months([2, 1]), $activation['activated']);
    $this->assertSame(0.75, $activation['rate']);

    // Z registered in 2000 and is not counted.
    $this->assertSame([
      '1d' => 0,
      '7d' => 0,
      '30d' => 3,
      '90d' => 0,
      '1y' => 0,
      'later' => 0,
      'never' => 1,
    ], $this->activityStats->firstApplicationHistogram($this->period));
  }

}
