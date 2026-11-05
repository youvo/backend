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
   * Tests the default period and the presets with their neighbors.
   */
  public function testDefaultsAndPresets(): void {
    $default = $this->period([]);
    $this->assertSame('2014-01-01 00:00', $this->local($default->from()));
    $this->assertSame('2027-01-01 00:00', $this->local($default->to()));
    $this->assertSame(['period' => 'all'], $default->query());

    $year = $this->period(['period' => 'year']);
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

    $first_quarter = $this->period(['period' => 'quarter', 'year' => '2026', 'quarter' => '1']);
    $this->assertSame('Q4 2025', $first_quarter->previous()->label());
    $this->assertSame('Q4 2026', $this->period(['period' => 'quarter', 'year' => '2026', 'quarter' => '4'])->previous()->next()->label());
    $this->assertSame('Q1 2027', $this->period(['period' => 'quarter', 'year' => '2026', 'quarter' => '4'])->next()->label());
    $this->assertSame('2027', $year->next()->label());

    $month = $this->period(['period' => 'month', 'year' => '2026', 'month' => '11']);
    $this->assertSame(StatsGranularity::Week, $month->granularity());
    $this->assertSame('November 2026', $month->label());
    // November 2026 starts on a Sunday and ends on a Monday: its first and
    // last week are clipped to a day.
    $this->assertSame(['2026-W44', '2026-W45', '2026-W46', '2026-W47', '2026-W48', '2026-W49'], array_keys($month->buckets()));
    $this->assertSame('October 2026', $month->previous()->label());
    $this->assertSame('December 2025', $this->period(['period' => 'month', 'year' => '2026', 'month' => '1'])->previous()->label());

    $all = $this->period(['period' => 'all']);
    $this->assertSame('2014-01-01 00:00', $this->local($all->from()));
    $this->assertSame(StatsGranularity::Year, $all->granularity());
    $this->assertSame('All time', $all->label());
    $this->assertSame(2014, array_key_first($all->buckets()));
    $this->assertSame(2026, array_key_last($all->buckets()));
    $this->assertSame(['period' => 'all'], $all->query());
    $this->assertSame('All time', $all->previous()->label());
    $this->assertSame('All time', $all->next()->label());
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

    $previous = $custom->previous();
    $this->assertSame('2026-03-25 00:00', $this->local($previous->from()));
    $this->assertSame('2026-03-28 00:00', $this->local($previous->to()));
    $this->assertSame(StatsGranularity::Day, $previous->granularity());
    $this->assertSame('31.03.2026 – 02.04.2026', $custom->next()->label());

    $long = $this->period(['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-06-30']);
    $this->assertSame(StatsGranularity::Month, $long->granularity());
    $longer = $this->period(['period' => 'custom', 'from' => '2024-01-01', 'to' => '2026-06-30']);
    $this->assertSame(StatsGranularity::Year, $longer->granularity());

    $march = $this->period(['period' => 'month', 'year' => '2026', 'month' => '3']);
    $this->assertSame(['period' => 'month', 'year' => '2026', 'month' => '3'], $march->query());
    $buckets = $march->buckets();
    $this->assertSame('2026-W09', array_key_first($buckets));
    $this->assertSame('2026-W14', array_key_last($buckets));

    // Custom ranges take days up to 31 days, weeks up to 92 and months up to
    // 366.
    $lengths = [
      ['2026-01-01', '2026-01-31', StatsGranularity::Day],
      ['2026-01-01', '2026-02-01', StatsGranularity::Week],
      ['2026-01-01', '2026-04-02', StatsGranularity::Week],
      ['2026-01-01', '2026-04-03', StatsGranularity::Month],
    ];
    foreach ($lengths as [$from, $to, $granularity]) {
      $this->assertSame($granularity, $this->period(['period' => 'custom', 'from' => $from, 'to' => $to])->granularity(), $from . ' – ' . $to);
    }

    // The day buckets of a custom March follow DST.
    $buckets = $this->period(['period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31'])->buckets();
    $this->assertCount(31, $buckets);
    $this->assertSame(23 * 3600, $buckets['2026-03-29'][1] - $buckets['2026-03-29'][0]);
    $this->assertSame($buckets['2026-03-29'][1], $buckets['2026-03-30'][0]);

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
   * Tests that a range covering a calendar unit yields that preset.
   */
  public function testAlignedRangesArePresets(): void {
    $presets = [
      'quarter' => [
        ['from' => '2026-07-01', 'to' => '2026-09-30'],
        ['period' => 'quarter', 'year' => '2026', 'quarter' => '3'],
        StatsGranularity::Week,
        'Q2 2026',
      ],
      'month' => [
        ['from' => '2026-02-01', 'to' => '2026-02-28'],
        ['period' => 'month', 'year' => '2026', 'month' => '2'],
        StatsGranularity::Week,
        'January 2026',
      ],
      'leap month' => [
        ['from' => '2024-02-01', 'to' => '2024-02-29'],
        ['period' => 'month', 'year' => '2024', 'month' => '2'],
        StatsGranularity::Week,
        'January 2024',
      ],
      'year' => [
        ['from' => '2024-01-01', 'to' => '2024-12-31'],
        ['period' => 'year', 'year' => '2024'],
        StatsGranularity::Month,
        '2023',
      ],
    ];
    foreach ($presets as $name => [$range, $preset_query, $granularity, $previous]) {
      $custom = $this->period($range);
      $preset = $this->period($preset_query);
      $this->assertSame($preset->query(), $custom->query(), $name);
      $this->assertSame($preset->label(), $custom->label(), $name);
      $this->assertSame($preset->from()->getTimestamp(), $custom->from()->getTimestamp(), $name);
      $this->assertSame($preset->to()->getTimestamp(), $custom->to()->getTimestamp(), $name);
      $this->assertSame($granularity, $custom->granularity(), $name);
      $this->assertSame($previous, $custom->previous()->label(), $name);
    }

    // Ranges that miss a calendar unit by a day stay custom.
    $unaligned = [
      ['from' => '2026-03-28', 'to' => '2026-03-30'],
      ['from' => '2026-02-01', 'to' => '2026-02-27'],
      ['from' => '2026-02-01', 'to' => '2026-03-01'],
      ['from' => '2026-07-02', 'to' => '2026-09-30'],
      ['from' => '2024-01-01', 'to' => '2025-01-01'],
    ];
    foreach ($unaligned as $range) {
      $this->assertSame(['period' => 'custom'] + $range, $this->period($range)->query(), json_encode($range));
    }

    // An explicit custom period stays custom, so the range form stays open.
    $explicit = ['period' => 'custom', 'from' => '2024-02-01', 'to' => '2024-02-29'];
    $this->assertSame($explicit, $this->period($explicit)->query());
    $this->assertSame(StatsPeriod::CUSTOM, $this->period($explicit)->type());
  }

  /**
   * Tests switching the type around the period being viewed.
   */
  public function testWithGranularity(): void {
    $switch = fn(array $query, string $type): array => $this->period($query)->withGranularity($type, $this->now())->query();

    // A past period switches around its last day.
    $year = ['period' => 'year', 'year' => '2024'];
    $this->assertSame(['period' => 'quarter', 'year' => '2024', 'quarter' => '4'], $switch($year, 'quarter'));
    $this->assertSame(['period' => 'month', 'year' => '2024', 'month' => '12'], $switch($year, 'month'));
    $quarter = ['period' => 'quarter', 'year' => '2024', 'quarter' => '2'];
    $this->assertSame(['period' => 'year', 'year' => '2024'], $switch($quarter, 'year'));
    $this->assertSame(['period' => 'month', 'year' => '2024', 'month' => '6'], $switch($quarter, 'month'));

    // A running period switches around today.
    $current = ['period' => 'year', 'year' => '2026'];
    $this->assertSame(['period' => 'quarter', 'year' => '2026', 'quarter' => '4'], $switch($current, 'quarter'));
    $this->assertSame(['period' => 'month', 'year' => '2026', 'month' => '11'], $switch($current, 'month'));
    $this->assertSame(['period' => 'month', 'year' => '2026', 'month' => '11'], $switch(['period' => 'all'], 'month'));
    $future = ['period' => 'year', 'year' => '2030'];
    $this->assertSame(['period' => 'quarter', 'year' => '2026', 'quarter' => '4'], $switch($future, 'quarter'));
    $this->assertSame(['period' => 'all'], $switch($quarter, 'all'));

    // Custom keeps the days, all time runs until today.
    $this->assertSame(['period' => 'custom', 'from' => '2024-04-01', 'to' => '2024-06-30'], $switch($quarter, 'custom'));
    $range = ['period' => 'custom', 'from' => '2025-03-05', 'to' => '2025-04-20'];
    $this->assertSame($range, $switch($range, 'custom'));
    $this->assertSame(['period' => 'quarter', 'year' => '2025', 'quarter' => '2'], $switch($range, 'quarter'));
    $this->assertSame(['period' => 'custom', 'from' => '2014-01-01', 'to' => '2026-11-05'], $switch(['period' => 'all'], 'custom'));
    $custom = $this->period(['period' => 'quarter', 'year' => '2024', 'quarter' => '2'])->withGranularity('custom', $this->now());
    $this->assertSame(StatsGranularity::Week, $custom->granularity());
  }

  /**
   * Tests the short chart labels of the buckets.
   */
  public function testBucketLabels(): void {
    $year = $this->period(['period' => 'year', 'year' => '2026']);
    $this->assertSame(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], $year->bucketLabels());

    // A quarter starting mid-week labels its first, clipped week too.
    $quarter = $this->period(['period' => 'quarter', 'year' => '2026', 'quarter' => '4']);
    $labels = $quarter->bucketLabels();
    $this->assertCount(count($quarter->buckets()), $labels);
    $this->assertSame(['W40', 'W41'], array_slice($labels, 0, 2));
    $this->assertSame('W53', end($labels));

    $month = $this->period(['period' => 'month', 'year' => '2026', 'month' => '11']);
    $this->assertSame(['W44', 'W45', 'W46', 'W47', 'W48', 'W49'], $month->bucketLabels());

    // Short custom ranges label days.
    $days = $this->period(['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-30']);
    $this->assertSame(['01.11.', '02.11.'], array_slice($days->bucketLabels(), 0, 2));
    $this->assertSame('30.11.', $days->bucketLabels()[29]);

    // Months get the year when the period spans more than one.
    $across = $this->period(['period' => 'custom', 'from' => '2025-10-15', 'to' => '2026-02-10']);
    $this->assertSame(StatsGranularity::Month, $across->granularity());
    $this->assertSame(['Oct 25', 'Nov 25', 'Dec 25', 'Jan 26', 'Feb 26'], $across->bucketLabels());

    // Year buckets are integer keys, their labels strings.
    $all = $this->period(['period' => 'all']);
    $labels = $all->bucketLabels();
    $this->assertSame('2014', $labels[0]);
    $this->assertSame('2026', end($labels));
  }

  /**
   * Tests that invalid arguments fall back to the all time period.
   */
  public function testInvalidInputFallsBack(): void {
    $default = $this->period(['period' => 'all']);
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
