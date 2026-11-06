<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\stats\Activity\ActivitySnapshots;
use Drupal\stats\StatsPeriod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the daily snapshots of active users per role.
 */
#[Group('stats')]
class ActivitySnapshotsTest extends StatsTestBase {

  /**
   * The state key of the last recorded day.
   */
  const string STATE_KEY = 'stats.activity_last_day';

  /**
   * The activity snapshots service.
   */
  protected ActivitySnapshots $snapshots;

  /**
   * The state value before the test, or NULL if it was not set.
   */
  protected ?string $originalLastDay;

  /**
   * Gets the snapshots service and remembers the last recorded day.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->snapshots = $this->container->get('stats.activity_snapshots');
    $this->originalLastDay = $this->container->get('state')->get(static::STATE_KEY);
  }

  /**
   * Restores the last recorded day.
   */
  protected function tearDown(): void {

    $state = $this->container->get('state');
    if ($this->originalLastDay === NULL) {
      $state->delete(static::STATE_KEY);
    }
    else {
      $state->set(static::STATE_KEY, $this->originalLastDay);
    }
    parent::tearDown();
  }

  /**
   * Tests the count of active users per role since a timestamp.
   */
  public function testCountActive(): void {

    $now = $this->container->get('datetime.time')->getRequestTime();
    $day_before = $this->snapshots->countActive($now - 86400);
    $month_before = $this->snapshots->countActive($now - 30 * 86400);

    $creative = $this->createCreative();
    $creative->setLastAccessTime($now)->save();
    $this->assertSame($day_before['creative'] + 1, $this->snapshots->countActive($now - 86400)['creative']);

    $creative->setLastAccessTime($now - 40 * 86400)->save();
    $this->assertSame($month_before['creative'], $this->snapshots->countActive($now - 30 * 86400)['creative']);

    $manager = $this->createCreative('creative', 'manager');
    $manager->setLastAccessTime($now)->save();
    $this->assertSame($day_before['creative'], $this->snapshots->countActive($now - 86400)['creative']);
  }

  /**
   * Tests that the daily snapshot is written once per local day.
   */
  public function testRecordDailyOncePerDay(): void {

    $timezone = $this->timezone();
    $now = $this->container->get('datetime.time')->getRequestTime();
    $yesterday = (new \DateTimeImmutable('@' . $now))->setTimezone($timezone)->modify('-1 day')->format('Y-m-d');
    $this->touchDay($yesterday);
    $this->container->get('state')->delete(static::STATE_KEY);

    $this->assertTrue($this->snapshots->recordDaily());
    $roles = $this->container->get('database')
      ->select('stats_activity', 'a')
      ->fields('a', ['role'])
      ->condition('day', $yesterday)
      ->orderBy('role')
      ->execute()
      ->fetchCol();
    $this->assertSame(['creative', 'organization', 'prospect'], $roles);

    $this->assertFalse($this->snapshots->recordDaily());
  }

  /**
   * Tests the active users per bucket of each granularity.
   */
  public function testSeries(): void {

    // The counts of the day, week, month, quarter and year up to each day.
    $rows = [
      // A Monday in W03.
      '2018-01-15' => [3, 10, 20, 20, 20],
      // A Wednesday in W05.
      '2018-01-31' => [5, 12, 30, 30, 30],
      // A Saturday in W06.
      '2018-02-10' => [2, 9, 15, 35, 40],
    ];
    foreach ($rows as $day => $counts) {
      $this->touchDay($day);
      $this->snapshots->write($day, 'creative', array_combine(ActivitySnapshots::COLUMNS, $counts));
    }
    $timezone = $this->timezone();
    $now = new \DateTimeImmutable('now', $timezone);
    $series = fn(array $query): array => $this->snapshots->series(StatsPeriod::fromQuery($query, $timezone, $now))['creative'];

    // A year by month takes the month count of the last day of each month.
    $year = $series(['period' => 'year', 'year' => '2018']);
    $this->assertCount(12, $year);
    $this->assertSame(30, $year['2018-01']);
    $this->assertSame(15, $year['2018-02']);
    $this->assertNull($year['2018-03']);

    // All time by year takes the year count.
    $this->assertSame(40, $series(['period' => 'all'])['2018'] ?? NULL);

    // A quarter by week takes the week count.
    $quarter = $series(['period' => 'quarter', 'year' => '2018', 'quarter' => '1']);
    $this->assertSame(10, $quarter['2018-W03']);
    $this->assertNull($quarter['2018-W04']);
    $this->assertSame(12, $quarter['2018-W05']);
    $this->assertSame(9, $quarter['2018-W06']);

    // A short range by day takes the day count.
    $days = $series(['period' => 'custom', 'from' => '2018-01-10', 'to' => '2018-02-09']);
    $this->assertCount(31, $days);
    $this->assertSame(3, $days['2018-01-15']);
    $this->assertSame(5, $days['2018-01-31']);
    $this->assertNull($days['2018-01-16']);
    $this->assertArrayNotHasKey('2018-02-10', $days);
  }

}
