<?php

namespace Drupal\Tests\stats\Unit;

use Drupal\stats\Activity\ActivityAggregator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the aggregation of visits into active users per day.
 */
#[Group('stats')]
#[CoversClass(ActivityAggregator::class)]
class ActivityAggregatorTest extends UnitTestCase {

  /**
   * Tests the day, week, month and year counts across the boundaries.
   */
  public function testAggregate(): void {

    $timezone = new \DateTimeZone('Europe/Berlin');
    $local = fn (string $time): int => (new \DateTimeImmutable($time, $timezone))->getTimestamp();
    $visits = [
      // The ISO week from Monday 2020-12-28 to Sunday 2021-01-03 spans New
      // Year. Visitor 3 comes at 00:30 local, still 2020 in UTC.
      [1, 'creative', $local('2020-12-28 10:00')],
      [2, 'creative', $local('2020-12-31 23:30')],
      [3, 'creative', $local('2021-01-01 00:30')],
      [1, 'creative', $local('2021-01-02 12:00')],
      [1, 'creative', $local('2021-01-02 18:00')],
      [4, 'creative', $local('2021-01-04 09:00')],
      // Around the DST switch on Sunday 2021-03-28.
      [5, 'creative', $local('2021-03-27 23:30')],
      [5, 'creative', $local('2021-03-28 23:30')],
      [6, 'creative', $local('2021-03-29 00:30')],
      [7, 'organization', $local('2021-01-03 12:00')],
    ];

    $rows = ActivityAggregator::aggregate($visits, $timezone);
    $row = fn (int $day, int $week, int $month, int $year): array => [
      'active_day' => $day,
      'active_week' => $week,
      'active_month' => $month,
      'active_year' => $year,
    ];

    $expected = [
      '2020-12-28' => $row(1, 1, 1, 1),
      '2020-12-29' => $row(0, 1, 1, 1),
      '2020-12-31' => $row(1, 2, 2, 2),
      // The week goes on, the month and the year start again.
      '2021-01-01' => $row(1, 3, 1, 1),
      '2021-01-02' => $row(1, 3, 2, 2),
      '2021-01-03' => $row(0, 3, 2, 2),
      // A new week.
      '2021-01-04' => $row(1, 1, 3, 3),
      '2021-01-31' => $row(0, 0, 3, 3),
      // A new month on a Monday.
      '2021-02-01' => $row(0, 0, 0, 3),
      '2021-03-27' => $row(1, 1, 1, 4),
      '2021-03-28' => $row(1, 1, 1, 4),
      '2021-03-29' => $row(1, 1, 2, 5),
    ];
    foreach ($expected as $day => $counts) {
      $this->assertSame($counts, $rows[$day]['creative'], $day);
    }
    $this->assertCount(92, $rows);
    $this->assertSame('2021-03-29', array_key_last($rows));

    // A role starts with its first visit.
    $this->assertArrayNotHasKey('organization', $rows['2021-01-02']);
    $this->assertSame($row(1, 1, 1, 1), $rows['2021-01-03']['organization']);
    $this->assertSame($row(0, 0, 1, 1), $rows['2021-01-04']['organization']);
    $this->assertSame($row(0, 0, 0, 1), $rows['2021-03-29']['organization']);
  }

}
