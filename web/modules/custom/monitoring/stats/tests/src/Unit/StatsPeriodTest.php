<?php

namespace Drupal\Tests\stats\Unit;

use Drupal\stats\StatsGranularity;
use Drupal\stats\StatsPeriod;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the stats period value object.
 */
#[Group('stats')]
#[CoversClass(StatsPeriod::class)]
class StatsPeriodTest extends UnitTestCase {

  /**
   * Creates the timezone used by all tests.
   */
  protected function timezone(): \DateTimeZone {
    return new \DateTimeZone('Europe/Berlin');
  }

  /**
   * Creates the fixed current time.
   */
  protected function now(): \DateTimeImmutable {
    return new \DateTimeImmutable('2026-11-05 10:00', $this->timezone());
  }

  /**
   * Creates a period from query arguments.
   */
  protected function period(array $query): StatsPeriod {
    return StatsPeriod::fromQuery($query, $this->timezone(), $this->now());
  }

  /**
   * Formats a date in the test timezone.
   */
  protected function local(\DateTimeImmutable $date): string {
    return $date->setTimezone($this->timezone())->format('Y-m-d H:i');
  }

  /**
   * Tests the default period and the presets with their comparisons.
   */
  public function testDefaultsAndPresets(): void {
    $year = $this->period([]);
    $this->assertSame('2026-01-01 00:00', $this->local($year->from()));
    $this->assertSame('2027-01-01 00:00', $this->local($year->to()));
    $this->assertSame(StatsGranularity::Month, $year->granularity());
    $this->assertSame('2026', $year->label());
    $buckets = $year->buckets();
    $this->assertCount(12, $buckets);
    $this->assertSame('2026-01', array_key_first($buckets));
    $this->assertSame('2026-12', array_key_last($buckets));
    $this->assertSame(['period' => 'year', 'year' => '2026'], $year->query());

    $quarter = $this->period(['period' => 'quarter', 'year' => '2026', 'quarter' => '3']);
    $this->assertSame('2026-07-01 00:00', $this->local($quarter->from()));
    $this->assertSame('2026-10-01 00:00', $this->local($quarter->to()));
    $this->assertSame(StatsGranularity::Week, $quarter->granularity());
    $this->assertSame('2026-W27', array_key_first($quarter->buckets()));
    $this->assertSame('Q3 2026', $quarter->label());

    $previous = $quarter->previous();
    $this->assertSame('2026-04-01 00:00', $this->local($previous->from()));
    $this->assertSame('2026-07-01 00:00', $this->local($previous->to()));
    $this->assertSame('Q2 2026', $previous->label());
    $last_year = $quarter->sameLastYear();
    $this->assertSame('2025-07-01 00:00', $this->local($last_year->from()));
    $this->assertSame('2025-10-01 00:00', $this->local($last_year->to()));
    $this->assertSame('Q3 2025', $last_year->label());

    $first_quarter = $this->period(['period' => 'quarter', 'year' => '2026', 'quarter' => '1']);
    $this->assertSame('Q4 2025', $first_quarter->previous()->label());

    $month = $this->period(['period' => 'month', 'year' => '2026', 'month' => '11']);
    $this->assertSame(StatsGranularity::Day, $month->granularity());
    $this->assertSame('November 2026', $month->label());
    $this->assertCount(30, $month->buckets());
    $this->assertSame('October 2026', $month->previous()->label());
    $this->assertSame('November 2025', $month->sameLastYear()->label());
    $this->assertSame('December 2025', $this->period(['period' => 'month', 'year' => '2026', 'month' => '1'])->previous()->label());

    $all = $this->period(['period' => 'all']);
    $this->assertSame('2014-01-01 00:00', $this->local($all->from()));
    $this->assertSame(StatsGranularity::Year, $all->granularity());
    $this->assertSame('All time', $all->label());
    $this->assertSame(2014, array_key_first($all->buckets()));
    $this->assertSame(2026, array_key_last($all->buckets()));
    $this->assertSame(['period' => 'all'], $all->query());
    $this->assertSame('All time', $all->previous()->label());
    $this->assertSame('All time', $all->sameLastYear()->label());
  }

  /**
   * Tests custom ranges, DST, instants around New Year and ISO weeks.
   */
  public function testBoundaries(): void {
    $custom = $this->period(['period' => 'custom', 'from' => '2026-03-28', 'to' => '2026-03-30']);
    $this->assertSame('2026-03-28 00:00', $this->local($custom->from()));
    $this->assertSame('2026-03-31 00:00', $this->local($custom->to()));
    $this->assertSame(StatsGranularity::Day, $custom->granularity());
    $this->assertSame('28.03.2026 – 30.03.2026', $custom->label());
    $this->assertSame(['period' => 'custom', 'from' => '2026-03-28', 'to' => '2026-03-30'], $custom->query());
    $buckets = $custom->buckets();
    $this->assertSame(['2026-03-28', '2026-03-29', '2026-03-30'], array_keys($buckets));
    $this->assertSame(23 * 3600, $buckets['2026-03-29'][1] - $buckets['2026-03-29'][0]);
    $this->assertSame($buckets['2026-03-29'][1], $buckets['2026-03-30'][0]);

    $previous = $custom->previous();
    $this->assertSame('2026-03-25 00:00', $this->local($previous->from()));
    $this->assertSame('2026-03-28 00:00', $this->local($previous->to()));
    $this->assertSame(StatsGranularity::Day, $previous->granularity());
    $last_year = $custom->sameLastYear();
    $this->assertSame('2025-03-28 00:00', $this->local($last_year->from()));
    $this->assertSame('2025-03-31 00:00', $this->local($last_year->to()));

    $long = $this->period(['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-06-30']);
    $this->assertSame(StatsGranularity::Month, $long->granularity());
    $longer = $this->period(['period' => 'custom', 'from' => '2024-01-01', 'to' => '2026-06-30']);
    $this->assertSame(StatsGranularity::Year, $longer->granularity());

    $instant = (new \DateTimeImmutable('2026-01-01 00:30', $this->timezone()))->getTimestamp();
    $year = $this->period(['period' => 'year', 'year' => '2026']);
    $this->assertSame('2026-01', $year->bucketOf($instant));
    $this->assertTrue($year->contains($instant));
    $previous_year = $this->period(['period' => 'year', 'year' => '2025']);
    $this->assertFalse($previous_year->contains($instant));
    $this->assertNull($previous_year->bucketOf($instant));
    $this->assertFalse($year->contains($year->to()->getTimestamp()));

    $quarter = $this->period(['period' => 'quarter', 'year' => '2026', 'quarter' => '4']);
    $buckets = $quarter->buckets();
    $this->assertSame('2026-W53', array_key_last($buckets));
    $this->assertSame($quarter->to()->getTimestamp(), end($buckets)[1]);
    $this->assertSame($quarter->from()->getTimestamp(), reset($buckets)[0]);
    $new_year_eve = (new \DateTimeImmutable('2026-12-31 12:00', $this->timezone()))->getTimestamp();
    $this->assertSame('2026-W53', $quarter->bucketOf($new_year_eve));
  }

  /**
   * Tests that invalid arguments fall back to the default year period.
   */
  public function testInvalidInputFallsBack(): void {
    $default = $this->period([]);
    $invalid = [
      ['from' => 'foo'],
      ['period' => 'custom', 'from' => 'foo', 'to' => '2026-01-01'],
      ['period' => 'custom', 'from' => '2026-02-31', 'to' => '2026-03-01'],
      ['period' => 'custom', 'from' => '2026-05-01'],
      ['from' => '2026-05-01', 'to' => '2026-04-01'],
      ['period' => 'decade'],
      ['quarter' => '7'],
      ['period' => 'quarter', 'quarter' => '7'],
      ['month' => '13'],
      ['period' => 'month', 'month' => '13'],
      ['period' => 'year', 'year' => 'abc'],
      ['period' => ['all']],
    ];
    foreach ($invalid as $query) {
      $period = $this->period($query);
      $this->assertSame($default->query(), $period->query(), json_encode($query));
      $this->assertSame($default->from()->getTimestamp(), $period->from()->getTimestamp());
      $this->assertSame($default->to()->getTimestamp(), $period->to()->getTimestamp());
    }
  }

}
