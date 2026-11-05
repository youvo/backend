<?php

namespace Drupal\Tests\stats\Unit;

use Drupal\stats\Activity\ActivityAggregator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the aggregation of legacy visits into daily active users.
 */
#[Group('stats')]
#[CoversClass(ActivityAggregator::class)]
class ActivityAggregatorTest extends UnitTestCase {

  /**
   * Tests the daily and 30-day counts per role.
   */
  public function testAggregate(): void {

    $timezone = new \DateTimeZone('Europe/Berlin');
    $local = fn (string $time): int => (new \DateTimeImmutable($time, $timezone))->getTimestamp();
    $visits = [
      [1, 'creative', $local('2020-01-01 10:00')],
      [1, 'creative', $local('2020-01-01 18:00')],
      [2, 'creative', $local('2020-01-02 12:00')],
      [3, 'organization', $local('2020-01-31 12:00')],
      [4, 'creative', (new \DateTimeImmutable('2020-01-01 23:30', new \DateTimeZone('UTC')))->getTimestamp()],
    ];

    $rows = ActivityAggregator::aggregate($visits, $timezone);

    $this->assertSame(['active_1d' => 1, 'active_30d' => 1], $rows['2020-01-01']['creative']);
    $this->assertSame(['active_1d' => 2, 'active_30d' => 3], $rows['2020-01-02']['creative']);
    $this->assertSame(['active_1d' => 0, 'active_30d' => 3], $rows['2020-01-03']['creative']);
    $this->assertSame(['active_1d' => 0, 'active_30d' => 3], $rows['2020-01-30']['creative']);
    $this->assertSame(['active_1d' => 0, 'active_30d' => 2], $rows['2020-01-31']['creative']);
    $this->assertCount(31, $rows);
    $this->assertArrayNotHasKey('2020-02-01', $rows);

    $this->assertSame(['active_1d' => 1, 'active_30d' => 1], $rows['2020-01-31']['organization']);
    $this->assertArrayNotHasKey('organization', $rows['2020-01-30']);
    $this->assertArrayNotHasKey('organization', $rows['2020-01-01']);
  }

}
