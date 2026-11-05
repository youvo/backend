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
   * Tests the active users per bucket.
   */
  public function testSeries(): void {

    $rows = [
      '2001-01-15' => [3, 10],
      '2001-01-31' => [5, 12],
      '2001-02-10' => [2, 9],
    ];
    foreach ($rows as $day => [$active_1d, $active_30d]) {
      $this->touchDay($day);
      $this->snapshots->write($day, 'creative', $active_1d, $active_30d);
    }
    $timezone = $this->timezone();
    $period = StatsPeriod::fromQuery(['period' => 'year', 'year' => '2001'], $timezone, new \DateTimeImmutable('now', $timezone));

    $series = $this->snapshots->series($period)['creative'];

    $this->assertCount(12, $series);
    $this->assertSame(12, $series['2001-01']);
    $this->assertSame(9, $series['2001-02']);
    $this->assertNull($series['2001-03']);
  }

}
